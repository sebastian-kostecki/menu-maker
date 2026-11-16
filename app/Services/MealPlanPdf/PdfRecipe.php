<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

final readonly class PdfRecipe
{
    /**
     * @param  list<PdfIngredientGroup>  $ingredientGroups
     */
    public function __construct(
        public string $name,
        public string $category,
        public string $categoryValue,
        public bool $deleted,
        public array $ingredientGroups,
        public string $instructions,
    ) {}
}
