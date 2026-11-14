<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling\Drivers;

use App\Services\IngredientScaling\IngredientRefiner;
use App\Services\IngredientScaling\RefinementItem;

/**
 * Deterministic driver for tests and E2E: pieces and grams/millilitres round half-up, kg and l stay as computed.
 */
class FakeIngredientRefiner implements IngredientRefiner
{
    public function refine(array $items): array
    {
        $proposals = [];

        /** @var RefinementItem $item */
        foreach ($items as $item) {
            $proposals[$item->key] = match ($item->unitCode) {
                'szt' => max('1', bcadd($item->computedQuantity, '0.5', 0)),
                'g', 'ml' => bcadd($item->computedQuantity, '0.5', 0),
                default => $item->computedQuantity,
            };
        }

        return $proposals;
    }
}
