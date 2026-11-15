<?php

declare(strict_types=1);

namespace App\Services\RecipeImport;

/**
 * One ingredient row already converted to one of the five allowed units.
 */
final readonly class ParsedIngredient
{
    /**
     * @param  numeric-string  $quantity  Quantity with two decimals, e.g. "250.00".
     */
    public function __construct(
        public string $name,
        public string $quantity,
        public string $unitCode,
    ) {}
}
