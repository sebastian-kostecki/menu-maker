<?php

declare(strict_types=1);

namespace App\Services\ShoppingList;

use App\Models\MealPlan;
use App\Models\Unit;
use Collator;

class ShoppingListBuilder
{
    private const SCALE = 12;

    public function __construct(private readonly QuantityFormatter $quantityFormatter = new QuantityFormatter) {}

    /**
     * Sum, convert, round and sort quantity lines into shopping list rows.
     *
     * @param  iterable<QuantityLine>  $lines
     * @return list<ShoppingListItem>
     */
    public function build(iterable $lines): array
    {
        /** @var array<string, array{ingredientId: int, name: string, group: string, sum: string}> $groups */
        $groups = [];

        foreach ($lines as $line) {
            $group = Unit::GROUPS[$line->unitCode] ?? $line->unitCode;
            $key = $line->ingredientId.'|'.$group;
            $amount = isset(Unit::GROUPS[$line->unitCode])
                ? bcmul($line->quantity, $line->factorToBase, self::SCALE)
                : bcadd($line->quantity, '0', self::SCALE);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'ingredientId' => $line->ingredientId,
                    'name' => $line->name,
                    'group' => $group,
                    'sum' => '0',
                ];
            }

            $groups[$key]['sum'] = bcadd($groups[$key]['sum'], $amount, self::SCALE);
        }

        $rows = [];

        foreach ($groups as $group) {
            $rows[] = ['item' => $this->makeItem($group), 'group' => $group['group']];
        }

        $collator = new Collator('pl_PL');
        usort($rows, function (array $a, array $b) use ($collator): int {
            $byName = $collator->compare($a['item']->name, $b['item']->name);

            if ($byName !== 0) {
                return $byName;
            }

            return $this->groupRank($a['group']) <=> $this->groupRank($b['group']);
        });

        return array_map(fn (array $row): ShoppingListItem => $row['item'], $rows);
    }

    /**
     * Build the shopping list for a plan from its recipes' ingredients as written.
     */
    public function forMealPlan(MealPlan $mealPlan): ShoppingList
    {
        $mealPlan->loadMissing('meals.recipe.recipeIngredients.ingredient', 'meals.recipe.recipeIngredients.unit');

        $lines = [];
        $withoutIngredients = [];

        foreach ($mealPlan->meals as $meal) {
            $recipe = $meal->recipe;

            if ($recipe === null) {
                continue;
            }

            if ($recipe->recipeIngredients->isEmpty()) {
                $withoutIngredients[$recipe->id] = $recipe->name;

                continue;
            }

            foreach ($recipe->recipeIngredients as $recipeIngredient) {
                $ingredient = $recipeIngredient->ingredient;
                $unit = $recipeIngredient->unit;

                if ($ingredient === null || $unit === null) {
                    continue;
                }

                $lines[] = new QuantityLine(
                    ingredientId: $ingredient->id,
                    name: $ingredient->name,
                    unitCode: $unit->code,
                    factorToBase: (string) $unit->conversion_factor_to_base,
                    quantity: (string) $recipeIngredient->quantity,
                );
            }
        }

        $names = array_values($withoutIngredients);
        (new Collator('pl_PL'))->sort($names);

        return new ShoppingList($this->build($lines), $names);
    }

    /**
     * @param  array{ingredientId: int, name: string, group: string, sum: string}  $group
     */
    private function makeItem(array $group): ShoppingListItem
    {
        $large = Unit::GROUP_LARGE_CODES[$group['group']] ?? null;
        $base = Unit::GROUP_BASE_CODES[$group['group']] ?? null;

        if ($base === null) {
            // Unknown unit code: own group keyed by the code, no conversion.
            $unit = $group['group'];
            $value = $group['sum'];
        } elseif ($large !== null && bccomp($group['sum'], Unit::LARGE_UNIT_THRESHOLD, self::SCALE) > 0) {
            $unit = $large;
            $value = bcdiv($group['sum'], Unit::LARGE_UNIT_THRESHOLD, self::SCALE);
        } else {
            $unit = $base;
            $value = $group['sum'];
        }

        $rounded = bcadd(bcadd($value, '0.005', self::SCALE), '0', 2);
        $quantity = str_contains($rounded, '.') ? rtrim(rtrim($rounded, '0'), '.') : $rounded;

        return new ShoppingListItem(
            ingredientId: $group['ingredientId'],
            name: $group['name'],
            quantity: $quantity,
            unit: $unit,
            display: $this->quantityFormatter->format($quantity, $unit),
        );
    }

    private function groupRank(string $group): int
    {
        $rank = array_search($group, Unit::GROUP_ORDER, true);

        return $rank === false ? count(Unit::GROUP_ORDER) : $rank;
    }
}
