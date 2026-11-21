<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

final readonly class MealPlanMenu
{
    /**
     * @param  list<string>  $dayLabels
     * @param  list<PdfMenuRow>  $rows
     */
    public function __construct(
        public string $title,
        public array $dayLabels,
        public array $rows,
    ) {}
}
