<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

final readonly class PdfIngredientGroup
{
    /**
     * @param  list<string>  $dayLabels
     * @param  list<string>  $lines
     */
    public function __construct(
        public array $dayLabels,
        public array $lines,
    ) {}
}
