<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Recipe
 */
class RecipeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'instructions' => $this->instructions,
            'calories' => $this->calories,
            'servings' => $this->servings,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'ingredients' => $this->whenLoaded('recipeIngredients', function () {
                return $this->recipeIngredients->map(function ($recipeIngredient) {
                    return [
                        'id' => $recipeIngredient->ingredient->id ?? null,
                        'name' => $recipeIngredient->ingredient->name ?? 'Unknown ingredient',
                        'quantity' => $recipeIngredient->quantity ?? 0,
                        'unit' => [
                            'id' => $recipeIngredient->unit->id ?? null,
                            'code' => $recipeIngredient->unit->code ?? 'pcs',
                        ],
                    ];
                });
            }, []),
        ];
    }
}
