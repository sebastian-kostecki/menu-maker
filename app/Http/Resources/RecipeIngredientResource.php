<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RecipeIngredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecipeIngredient
 */
class RecipeIngredientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ingredient_id' => $this->ingredient_id,
            'ingredient_name' => optional($this->ingredient)->name,
            'quantity' => $this->quantity,
            'unit_id' => $this->unit_id,
            'unit_code' => optional($this->unit)->code,
        ];
    }
}
