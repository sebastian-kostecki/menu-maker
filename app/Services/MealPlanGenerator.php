<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MealCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

class MealPlanGenerator
{
    /**
     * Meal slots per category in a 7-day plan.
     */
    public const MEALS_PER_CATEGORY = 7;

    public const NO_RECIPES_MESSAGE = 'Nie masz jeszcze żadnego przepisu. Dodaj przepis, żeby wygenerować jadłospis.';

    /**
     * Whether the user has at least one non-deleted recipe.
     */
    public function hasAnyRecipe(int $userId): bool
    {
        return Recipe::forUser($userId)->exists();
    }

    /**
     * Non-deleted recipes per category, every category key present.
     *
     * @return array<string, int>
     */
    public function recipeCountsPerCategory(int $userId): array
    {
        $counts = [];

        foreach (MealCategory::values() as $category) {
            $counts[$category] = Recipe::forUser($userId)->byCategory($category)->count();
        }

        return $counts;
    }

    /**
     * Replace the plan's meals with a random draw of distinct recipes per category.
     * Each category fills up to 7 random days; the remaining days get a meal without a recipe.
     *
     * @throws InsufficientRecipesException
     */
    public function generate(MealPlan $mealPlan): void
    {
        if (! $this->hasAnyRecipe($mealPlan->user_id)) {
            throw new InsufficientRecipesException(self::NO_RECIPES_MESSAGE);
        }

        DB::transaction(function () use ($mealPlan): void {
            $mealPlan->meals()->delete();

            $drawn = 0;

            foreach (MealCategory::values() as $category) {
                $recipes = Recipe::forUser($mealPlan->user_id)
                    ->byCategory($category)
                    ->inRandomOrder()
                    ->limit(self::MEALS_PER_CATEGORY)
                    ->get()
                    ->values();

                $drawn += $recipes->count();

                $offsets = range(0, self::MEALS_PER_CATEGORY - 1);
                shuffle($offsets);

                foreach ($offsets as $index => $offset) {
                    Meal::create([
                        'meal_plan_id' => $mealPlan->id,
                        'recipe_id' => $recipes->get($index)?->id,
                        'meal_date' => $mealPlan->start_date->copy()->addDays($offset),
                        'meal_category' => $category,
                    ]);
                }
            }

            // Recipes deleted after the check above: roll back and keep the old meals.
            if ($drawn === 0) {
                throw new InsufficientRecipesException(self::NO_RECIPES_MESSAGE);
            }
        });
    }
}
