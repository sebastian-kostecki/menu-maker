<?php

declare(strict_types=1);

namespace App\Services\ShoppingList;

final readonly class ShoppingListItem
{
    /**
     * @param  string  $quantity  Rounded dot-decimal string without trailing zeros.
     * @param  string  $unit  Display unit code (e.g. "kg", "szt").
     * @param  string  $display  Polish text, e.g. "1,23 kg".
     */
    public function __construct(
        public int $ingredientId,
        public string $name,
        public string $quantity,
        public string $unit,
        public string $display,
    ) {}
}
