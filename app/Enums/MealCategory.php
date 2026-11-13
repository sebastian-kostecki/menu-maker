<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Meal categories of recipes and meals, declared in display order.
 *
 * The values match `recipes.category` and `meals.meal_category`. The mapping is display-only:
 * `supper` is shown as Obiad and `dinner` as Kolacja. Mirrored on the frontend by
 * `resources/js/lib/mealCategories.js`.
 */
enum MealCategory: string
{
    case Breakfast = 'breakfast';
    case Supper = 'supper';
    case Dinner = 'dinner';

    /**
     * Polish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Breakfast => 'Śniadanie',
            self::Supper => 'Obiad',
            self::Dinner => 'Kolacja',
        };
    }

    /**
     * Raw values in display order.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $category): string => $category->value, self::cases());
    }

    /**
     * Position of a raw value in display order, for sorting.
     */
    public static function displayIndex(string $value): int
    {
        $index = array_search($value, self::values(), true);

        return $index === false ? PHP_INT_MAX : $index;
    }
}
