<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\ShoppingList\ShoppingList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShoppingList
 */
class ShoppingListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items' => array_map(fn ($item): array => [
                'ingredient_id' => $item->ingredientId,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'display' => $item->display,
            ], $this->items),
            'recipes_without_ingredients' => $this->recipesWithoutIngredients,
        ];
    }
}
