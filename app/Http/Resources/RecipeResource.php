<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'ingredients' => RecipeIngredientResource::collection($this->whenLoaded('recipeIngredients')),
        ];
    }
}
