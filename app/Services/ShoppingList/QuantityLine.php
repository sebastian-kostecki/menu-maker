<?php

declare(strict_types=1);

namespace App\Services\ShoppingList;

final readonly class QuantityLine
{
    /**
     * @param  string  $factorToBase  Decimal string, e.g. "1000.0000" for kg.
     * @param  string  $quantity  Decimal string with at most 2 decimals.
     */
    public function __construct(
        public int $ingredientId,
        public string $name,
        public string $unitCode,
        public string $factorToBase,
        public string $quantity,
    ) {}
}
