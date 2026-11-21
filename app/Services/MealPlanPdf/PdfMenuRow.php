<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

final readonly class PdfMenuRow
{
    /** Cell text for a meal without a recipe; the template prints it muted. */
    public const EMPTY_SLOT = 'Bez posiłku';

    /**
     * @param  list<string>  $cells  One entry per day label: a recipe name, "Bez posiłku" or '' (no meal that day).
     */
    public function __construct(
        public string $category,
        public string $categoryValue,
        public array $cells,
    ) {}
}
