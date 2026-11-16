<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMealRequest;
use App\Http\Resources\MealPlanResource;
use App\Models\Meal;
use App\Models\MealPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class MealPlanMealController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Meal::class, 'meal');
    }

    /**
     * Swap the recipe of one meal of a draft.
     */
    public function update(UpdateMealRequest $request, MealPlan $mealPlan, Meal $meal): JsonResponse|RedirectResponse
    {
        // One conditional update, so a swap that loses to an approval fails instead of
        // changing a meal the job is scaling.
        $updated = Meal::query()
            ->whereKey($meal->getKey())
            ->whereHas('mealPlan', fn ($query) => $query->where('status', 'draft'))
            ->update([
                'recipe_id' => $request->integer('recipe_id'),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'status' => UpdateMealRequest::NOT_DRAFT_MESSAGE,
            ]);
        }

        if (! $request->expectsJson()) {
            return back();
        }

        $mealPlan->refresh()->load('meals.recipe');

        return response()->json([
            'message' => 'Meal recipe swapped.',
            'data' => new MealPlanResource($mealPlan),
        ]);
    }
}
