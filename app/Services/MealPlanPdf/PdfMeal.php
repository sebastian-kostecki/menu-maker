<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

final readonly class PdfMeal
{
    /**
     * @param  list<string>  $ingredients
     */
    public function __construct(
        public string $category,
        public string $recipeName,
        public bool $recipeDeleted,
        public array $ingredients,
        public string $instructions,
    ) {}
}
