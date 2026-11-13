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
    public const MEALS_PER_CATEGORY = 7;

    /**
     * Count of recipes the user lacks, only for categories that are short.
     *
     * @return array<string, int>
     */
    public function missingRecipesPerCategory(int $userId): array
    {
        $missing = [];

        foreach (MealCategory::values() as $category) {
            $have = Recipe::forUser($userId)->byCategory($category)->count();

            if ($have < self::MEALS_PER_CATEGORY) {
                $missing[$category] = self::MEALS_PER_CATEGORY - $have;
            }
        }

        return $missing;
    }

    /**
     * One Polish message per short category.
     *
     * @param  array<string, int>  $missing
     * @return list<string>
     */
    public function shortfallMessages(array $missing): array
    {
        $messages = [];

        foreach ($missing as $category => $lacking) {
            $messages[] = sprintf(
                'Za mało przepisów w kategorii %s: masz %d, potrzeba %d. Dodaj jeszcze %d.',
                MealCategory::tryFrom($category)?->label() ?? $category,
                self::MEALS_PER_CATEGORY - $lacking,
                self::MEALS_PER_CATEGORY,
                $lacking,
            );
        }

        return $messages;
    }

    /**
     * Replace the plan's meals with a random draw of distinct recipes per category.
     *
     * @throws InsufficientRecipesException
     */
    public function generate(MealPlan $mealPlan): void
    {
        $missing = $this->missingRecipesPerCategory($mealPlan->user_id);

        if ($missing !== []) {
            throw new InsufficientRecipesException(
                $missing,
                implode(' ', $this->shortfallMessages($missing)),
            );
        }

        DB::transaction(function () use ($mealPlan): void {
            $mealPlan->meals()->delete();

            foreach (MealCategory::values() as $category) {
                $recipes = Recipe::forUser($mealPlan->user_id)
                    ->byCategory($category)
                    ->inRandomOrder()
                    ->limit(self::MEALS_PER_CATEGORY)
                    ->get();

                // Recipes deleted after the check above: roll back instead of saving a week with gaps.
                if ($recipes->count() < self::MEALS_PER_CATEGORY) {
                    $missing = [$category => self::MEALS_PER_CATEGORY - $recipes->count()];

                    throw new InsufficientRecipesException(
                        $missing,
                        implode(' ', $this->shortfallMessages($missing)),
                    );
                }

                foreach ($recipes->values() as $offset => $recipe) {
                    Meal::create([
                        'meal_plan_id' => $mealPlan->id,
                        'recipe_id' => $recipe->id,
                        'meal_date' => $mealPlan->start_date->copy()->addDays($offset),
                        'meal_category' => $category,
                    ]);
                }
            }
        });
    }
}
