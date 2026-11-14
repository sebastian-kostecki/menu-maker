<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

/**
 * One distinct (ingredient, unit, computed quantity) of a plan, sent to the AI once however many meals use it.
 */
final readonly class RefinementItem
{
    public string $key;

    public function __construct(
        public int $ingredientId,
        public string $name,
        public string $unitCode,
        public string $computedQuantity,
    ) {
        $this->key = self::keyFor($ingredientId, $unitCode, $computedQuantity);
    }

    public static function keyFor(int $ingredientId, string $unitCode, string $computedQuantity): string
    {
        return "i{$ingredientId}-{$unitCode}-{$computedQuantity}";
    }
}
