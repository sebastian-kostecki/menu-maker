<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

final readonly class PdfDay
{
    /**
     * @param  list<PdfMeal>  $meals
     */
    public function __construct(
        public string $label,
        public array $meals,
    ) {}
}
