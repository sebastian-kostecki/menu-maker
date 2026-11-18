<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MoveMealRequest;
use App\Http\Requests\UpdateMealRequest;
use App\Http\Resources\MealPlanResource;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Services\MealLinks\MealLinkRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MealPlanMealController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Meal::class, 'meal');
    }

    /**
     * Swap, empty or fill the recipe of one meal of a draft (recipe_id), or link it to the previous day's meal (from_meal_id).
     */
    public function update(UpdateMealRequest $request, MealPlan $mealPlan, Meal $meal, MealLinkRules $links): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($request, $mealPlan, $meal, $links): void {
            // Lock the plan row, so an approval waits for this edit and then scales its result.
            // Everything below is read fresh: the route-bound models were loaded before the lock.
            $plan = MealPlan::query()->whereKey($mealPlan->getKey())->lockForUpdate()->first();

            if ($plan === null || $plan->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => UpdateMealRequest::NOT_DRAFT_MESSAGE,
                ]);
            }

            $current = Meal::query()->where('meal_plan_id', $plan->id)->findOrFail($meal->getKey());

            if ($request->has('from_meal_id')) {
                $this->link($current, $request->integer('from_meal_id'), $links);

                return;
            }

            $this->changeRecipe($current, $request->validated('recipe_id'));
        });

        if (! $request->expectsJson()) {
            return back();
        }

        $mealPlan->refresh()->load('meals.recipe');

        return response()->json([
            'message' => 'Meal recipe swapped.',
            'data' => new MealPlanResource($mealPlan),
        ]);
    }

    /**
     * Exchange the recipes of a meal and another meal of the same draft (an empty target just receives the recipe).
     */
    public function move(MoveMealRequest $request, MealPlan $mealPlan, Meal $meal): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($request, $mealPlan, $meal): void {
            $plan = MealPlan::query()->whereKey($mealPlan->getKey())->lockForUpdate()->first();

            if ($plan === null || $plan->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => UpdateMealRequest::NOT_DRAFT_MESSAGE,
                ]);
            }

            $source = Meal::query()->where('meal_plan_id', $plan->id)->findOrFail($meal->getKey());
            $target = Meal::query()->where('meal_plan_id', $plan->id)->findOrFail($request->integer('target_meal_id'));

            // Same meal or same recipe (every linked pair shares one): nothing moves, and no link may be dropped.
            if ($source->is($target) || $source->recipe_id === $target->recipe_id) {
                return;
            }

            $sourceRecipeId = $source->recipe_id;
            $targetRecipeId = $target->recipe_id;

            $this->changeRecipe($source, $targetRecipeId);
            $this->changeRecipe($target->refresh(), $sourceRecipeId);
        });

        if (! $request->expectsJson()) {
            return back();
        }

        $mealPlan->refresh()->load('meals.recipe');

        return response()->json([
            'message' => 'Meal moved.',
            'data' => new MealPlanResource($mealPlan),
        ]);
    }

    /**
     * Make $meal the carried-over meal of the cooked meal $fromMealId.
     */
    private function link(Meal $meal, int $fromMealId, MealLinkRules $links): void
    {
        $from = Meal::query()->where('meal_plan_id', $meal->meal_plan_id)->find($fromMealId);

        if ($from === null || ! $links->canFeed($from, $meal)) {
            throw ValidationException::withMessages([
                'from_meal_id' => UpdateMealRequest::LINK_NOT_ALLOWED_MESSAGE,
            ]);
        }

        if ($from->feeds_meal_id === $meal->id) {
            return;
        }

        $meal->update(['recipe_id' => $from->recipe_id]);
        $from->update(['feeds_meal_id' => $meal->id]);
    }

    /**
     * Write the new recipe and keep the link consistent: changing a cooked meal empties its carried-over meal,
     * changing a carried-over meal only drops the link. Picking a carried-over meal's own recipe from the list
     * means "cook it fresh that day", so it drops the link too; elsewhere the current recipe is a no-op.
     */
    private function changeRecipe(Meal $meal, int|string|null $recipeId): void
    {
        $recipeId = $recipeId === null ? null : (int) $recipeId;
        $isCarriedOver = Meal::query()->where('feeds_meal_id', $meal->id)->exists();

        if ($meal->recipe_id === $recipeId && ! $isCarriedOver) {
            return;
        }

        if ($meal->feeds_meal_id !== null) {
            Meal::query()->whereKey($meal->feeds_meal_id)->update(['recipe_id' => null, 'updated_at' => now()]);
            $meal->feeds_meal_id = null;
        }

        Meal::query()->where('feeds_meal_id', $meal->id)->update(['feeds_meal_id' => null, 'updated_at' => now()]);

        $meal->recipe_id = $recipeId;
        $meal->save();
    }
}
