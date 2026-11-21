<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

final readonly class PdfMenuRow
{
    /**
     * @param  list<string>  $cells  One entry per day label: a recipe name, "Bez posiłku" or '' (no meal that day).
     */
    public function __construct(
        public string $category,
        public string $categoryValue,
        public array $cells,
    ) {}
}
