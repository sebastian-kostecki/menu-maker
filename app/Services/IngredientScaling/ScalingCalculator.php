<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

/**
 * Turns the family's daily energy need and a recipe's total kcal into a scaling factor.
 * Arithmetic is bcmath at scale 12; the only rounding is half-up to 2 decimals at output.
 */
final class ScalingCalculator
{
    /**
     * Share of daily kcal per stored meal category (supper is shown as Obiad, dinner as Kolacja).
     *
     * @var array<string, string>
     */
    public const MEAL_SHARES = ['breakfast' => '0.30', 'supper' => '0.40', 'dinner' => '0.30'];

    private const SCALE = 12;

    /**
     * Factor = family kcal for the meal / recipe total kcal, or null when the recipe has no kcal.
     */
    public function factor(string $familyDailyKcal, string $category, string $recipeCalories): ?string
    {
        if (bccomp($recipeCalories, '0', self::SCALE) <= 0) {
            return null;
        }

        $mealKcal = bcmul($familyDailyKcal, self::MEAL_SHARES[$category], self::SCALE);

        return bcdiv($mealKcal, $recipeCalories, self::SCALE);
    }

    /**
     * Multiply a quantity by a factor, rounded half up to 2 decimals.
     */
    public function scale(string $quantity, string $factor): string
    {
        return bcadd(bcmul($quantity, $factor, self::SCALE), '0.005', 2);
    }
}
