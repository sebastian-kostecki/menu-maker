<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

use App\Enums\MealCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Services\ShoppingList\QuantityFormatter;
use App\Services\ShoppingList\ShoppingListBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class MealPlanPdfExporter
{
    public function __construct(
        private readonly ShoppingListBuilder $shoppingListBuilder,
        private readonly QuantityFormatter $quantityFormatter,
    ) {}

    /**
     * Build the typed document the PDF template renders.
     */
    public function document(MealPlan $mealPlan): MealPlanDocument
    {
        $mealPlan->loadMissing('meals.recipe.recipeIngredients.ingredient', 'meals.recipe.recipeIngredients.unit');

        $byDate = $mealPlan->meals
            ->filter(fn (Meal $meal): bool => $meal->recipe !== null)
            ->groupBy(fn (Meal $meal): string => $meal->meal_date->format('Y-m-d'))
            ->sortKeys();

        $days = [];

        foreach ($byDate as $date => $meals) {
            $day = $meals->first()->meal_date->copy()->locale('pl');

            $pdfMeals = $meals
                ->sortBy(fn (Meal $meal): int => MealCategory::displayIndex($meal->meal_category))
                ->map(fn (Meal $meal): PdfMeal => $this->pdfMeal($meal))
                ->values()
                ->all();

            $days[] = new PdfDay(
                Str::ucfirst($day->isoFormat('dddd')).' '.$day->format('d.m'),
                $pdfMeals,
            );
        }

        return new MealPlanDocument(
            'Jadłospis '.$mealPlan->start_date->format('d.m.Y').' – '.$mealPlan->end_date->format('d.m.Y'),
            $days,
            $this->shoppingListBuilder->forMealPlan($mealPlan),
        );
    }

    /**
     * Render the plan and its shopping list as PDF bytes.
     */
    public function render(MealPlan $mealPlan): string
    {
        return Pdf::loadView('pdf.meal-plan', ['document' => $this->document($mealPlan)])
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isPhpEnabled', false)
            ->output();
    }

    public function filename(MealPlan $mealPlan): string
    {
        return 'jadlospis-'.$mealPlan->start_date->format('Y-m-d').'-'.$mealPlan->end_date->format('Y-m-d').'.pdf';
    }

    private function pdfMeal(Meal $meal): PdfMeal
    {
        $recipe = $meal->recipe;
        $ingredients = [];

        foreach ($recipe->recipeIngredients->sortBy('id') as $recipeIngredient) {
            $ingredient = $recipeIngredient->ingredient;
            $unit = $recipeIngredient->unit;

            if ($ingredient === null || $unit === null) {
                continue;
            }

            $ingredients[] = $ingredient->name.' — '.$this->quantityFormatter->format((string) $recipeIngredient->quantity, $unit->code);
        }

        return new PdfMeal(
            MealCategory::from($meal->meal_category)->label(),
            $recipe->name,
            $recipe->trashed(),
            $ingredients,
            (string) $recipe->instructions,
        );
    }
}
