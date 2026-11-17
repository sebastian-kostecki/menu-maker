<?php

declare(strict_types=1);

namespace App\Services\MealLinks;

use App\Models\Meal;

/**
 * Decides which meal may feed which ("cooked once, eaten twice"): an Obiad (supper) for the next day's Obiad, and a
 * Kolacja (dinner) for the next day's Śniadanie (breakfast). Keep PAIRS in sync with resources/js/lib/mealLinks.js.
 */
class MealLinkRules
{
    /**
     * Allowed links: the `from` meal, cooked on day D, feeds the `to` meal `days` days later.
     *
     * @var list<array{from: string, to: string, days: int, cooked_label: string, carried_label: string, use_suffix: string}>
     */
    public const PAIRS = [
        ['from' => 'supper', 'to' => 'supper', 'days' => 1, 'cooked_label' => 'Na 2 dni', 'carried_label' => 'Z wczoraj', 'use_suffix' => 'na 2 dni'],
        ['from' => 'dinner', 'to' => 'breakfast', 'days' => 1, 'cooked_label' => 'Też na śniadanie', 'carried_label' => 'Z kolacji', 'use_suffix' => 'też na śniadanie'],
    ];

    /**
     * The pair whose cooked meal is of this category.
     *
     * @return array{from: string, to: string, days: int, cooked_label: string, carried_label: string, use_suffix: string}|null
     */
    public function cookedPairFor(string $category): ?array
    {
        foreach (self::PAIRS as $pair) {
            if ($pair['from'] === $category) {
                return $pair;
            }
        }

        return null;
    }

    /**
     * The pair whose carried-over meal is of this category.
     *
     * @return array{from: string, to: string, days: int, cooked_label: string, carried_label: string, use_suffix: string}|null
     */
    public function carriedPairFor(string $category): ?array
    {
        foreach (self::PAIRS as $pair) {
            if ($pair['to'] === $category) {
                return $pair;
            }
        }

        return null;
    }

    /**
     * Whether $from may feed $to. Reads the link columns of the given models and the database for "fed by another".
     */
    public function canFeed(Meal $from, Meal $to): bool
    {
        if ($from->meal_plan_id !== $to->meal_plan_id || $from->is($to) || $from->recipe_id === null) {
            return false;
        }

        if (! $this->matchesPair($from, $to)) {
            return false;
        }

        if ($to->feeds_meal_id !== null) {
            return false;
        }

        if (Meal::query()->where('feeds_meal_id', $from->id)->exists()) {
            return false;
        }

        return $from->feeds_meal_id === null || $from->feeds_meal_id === $to->id;
    }

    /**
     * Ids of the carried-over meals (the ones another meal feeds) among already-loaded meals. A meal counts only
     * while it shares the cooked meal's recipe, so a broken link never hides a meal's own ingredients.
     *
     * @param  iterable<Meal>  $meals
     * @return array<int, true>
     */
    public function fedMealIds(iterable $meals): array
    {
        $recipeIds = [];

        foreach ($meals as $meal) {
            $recipeIds[$meal->id] = $meal->recipe_id;
        }

        $ids = [];

        foreach ($meals as $meal) {
            $fedId = $meal->feeds_meal_id;

            if ($fedId !== null && $meal->recipe_id !== null && ($recipeIds[$fedId] ?? null) === $meal->recipe_id) {
                $ids[$fedId] = true;
            }
        }

        return $ids;
    }

    /**
     * How many meals one cooking covers: 2 when the meal feeds another, otherwise 1.
     */
    public function servingsFor(Meal $meal): int
    {
        return $meal->feeds_meal_id === null ? 1 : 2;
    }

    private function matchesPair(Meal $from, Meal $to): bool
    {
        $distance = (int) $from->meal_date->diffInDays($to->meal_date, false);

        foreach (self::PAIRS as $pair) {
            if ($pair['from'] === $from->meal_category && $pair['to'] === $to->meal_category && $pair['days'] === $distance) {
                return true;
            }
        }

        return false;
    }
}
