<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

use App\Services\ShoppingList\ShoppingList;

final readonly class MealPlanDocument
{
    /**
     * @param  list<PdfDay>  $days
     * @param  list<string>  $notices
     * @param  list<PdfRecipe>  $recipes
     */
    public function __construct(
        public string $title,
        public array $days,
        public ShoppingList $shoppingList,
        public array $notices = [],
        public array $recipes = [],
    ) {}
}
