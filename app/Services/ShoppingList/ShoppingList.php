<?php

declare(strict_types=1);

namespace App\Services\ShoppingList;

final readonly class ShoppingList
{
    /**
     * @param  list<ShoppingListItem>  $items
     * @param  list<string>  $recipesWithoutIngredients
     */
    public function __construct(
        public array $items,
        public array $recipesWithoutIngredients,
    ) {}
}
