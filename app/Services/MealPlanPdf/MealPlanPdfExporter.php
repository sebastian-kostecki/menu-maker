<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

use App\Enums\MealCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Services\IngredientScaling\ScalingNotices;
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
    public function document(MealPlan $mealPlan, bool $withRecipes = false): MealPlanDocument
    {
        $mealPlan->loadMissing('meals.recipe', 'meals.ingredients.ingredient', 'meals.ingredients.unit');

        $byDate = $mealPlan->meals
            ->filter(fn (Meal $meal): bool => $meal->recipe !== null)
            ->groupBy(fn (Meal $meal): string => $meal->meal_date->format('Y-m-d'))
            ->sortKeys();

        $days = [];
        /** @var array<int, array{recipe: Recipe, groups: array<string, array{days: list<string>, lines: list<string>}>}> $recipeUses */
        $recipeUses = [];

        foreach ($byDate as $meals) {
            $day = $meals->first()->meal_date->copy()->locale('pl');
            $dayLabel = Str::ucfirst($day->isoFormat('dddd')).' '.$day->format('d.m');
            $pdfMeals = [];

            $sortedMeals = $meals->sortBy(fn (Meal $meal): int => MealCategory::displayIndex($meal->meal_category));

            foreach ($sortedMeals as $meal) {
                $lines = $this->ingredientLines($meal);
                $pdfMeals[] = $this->pdfMeal($meal, $lines);

                if (! $withRecipes) {
                    continue;
                }

                $groupKey = implode("\n", $lines);
                $recipeUses[$meal->recipe_id] ??= ['recipe' => $meal->recipe, 'groups' => []];
                $recipeUses[$meal->recipe_id]['groups'][$groupKey] ??= ['days' => [], 'lines' => $lines];

                if (! in_array($dayLabel, $recipeUses[$meal->recipe_id]['groups'][$groupKey]['days'], true)) {
                    $recipeUses[$meal->recipe_id]['groups'][$groupKey]['days'][] = $dayLabel;
                }
            }

            $days[] = new PdfDay($dayLabel, $pdfMeals);
        }

        return new MealPlanDocument(
            'Jadłospis '.$mealPlan->start_date->format('d.m.Y').' – '.$mealPlan->end_date->format('d.m.Y'),
            $days,
            $this->shoppingListBuilder->forMealPlan($mealPlan),
            ScalingNotices::for($mealPlan)['notices'],
            array_values(array_map($this->pdfRecipe(...), $recipeUses)),
        );
    }

    /**
     * Render the plan and its shopping list (and, on request, one page per recipe) as PDF bytes.
     */
    public function render(MealPlan $mealPlan, bool $withRecipes = false): string
    {
        return Pdf::loadView('pdf.meal-plan', ['document' => $this->document($mealPlan, $withRecipes)])
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isPhpEnabled', false)
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function filename(MealPlan $mealPlan): string
    {
        return 'jadlospis-'.$mealPlan->start_date->format('Y-m-d').'-'.$mealPlan->end_date->format('Y-m-d').'.pdf';
    }

    /**
     * @return list<string>
     */
    private function ingredientLines(Meal $meal): array
    {
        $lines = [];

        foreach ($meal->ingredients as $mealIngredient) {
            $ingredient = $mealIngredient->ingredient;
            $unit = $mealIngredient->unit;

            if ($ingredient === null || $unit === null || bccomp((string) $mealIngredient->quantity, '0', 2) === 0) {
                continue;
            }

            $lines[] = $ingredient->name.' — '.$this->quantityFormatter->format((string) $mealIngredient->quantity, $unit->code);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $ingredients
     */
    private function pdfMeal(Meal $meal, array $ingredients): PdfMeal
    {
        $recipe = $meal->recipe;
        $category = MealCategory::from($meal->meal_category);

        return new PdfMeal(
            $category->label(),
            $category->value,
            $recipe->name,
            $recipe->trashed(),
            $ingredients,
        );
    }

    /**
     * @param  array{recipe: Recipe, groups: array<string, array{days: list<string>, lines: list<string>}>}  $use
     */
    private function pdfRecipe(array $use): PdfRecipe
    {
        $recipe = $use['recipe'];
        $category = MealCategory::from($recipe->category);

        return new PdfRecipe(
            $recipe->name,
            $category->label(),
            $category->value,
            $recipe->trashed(),
            array_values(array_map(
                fn (array $group): PdfIngredientGroup => new PdfIngredientGroup($group['days'], $group['lines']),
                $use['groups'],
            )),
            (string) $recipe->instructions,
        );
    }
}
