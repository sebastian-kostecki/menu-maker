<?php

declare(strict_types=1);

namespace App\Services\MealPlanPdf;

use App\Enums\MealCategory;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Services\IngredientScaling\ScalingNotices;
use App\Services\MealLinks\MealLinkRules;
use App\Services\ShoppingList\QuantityFormatter;
use App\Services\ShoppingList\ShoppingListBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class MealPlanPdfExporter
{
    /** Longest recipe name (in characters) printed in a menu cell; longer names are cut with an ellipsis so the page stays single. */
    public const MENU_NAME_LIMIT = 60;

    public function __construct(
        private readonly ShoppingListBuilder $shoppingListBuilder,
        private readonly QuantityFormatter $quantityFormatter,
        private readonly MealLinkRules $links = new MealLinkRules,
    ) {}

    /**
     * Build the typed document the PDF template renders.
     */
    public function document(MealPlan $mealPlan, bool $withRecipes = false): MealPlanDocument
    {
        $mealPlan->loadMissing('meals.recipe', 'meals.ingredients.ingredient', 'meals.ingredients.unit');

        $carriedOver = $this->links->fedMealIds($mealPlan->meals);

        $byDate = $mealPlan->meals
            ->groupBy(fn (Meal $meal): string => $meal->meal_date->format('Y-m-d'))
            ->sortKeys();

        $days = [];
        /** @var array<int, array{recipe: Recipe, groups: array<string, array{days: list<string>, lines: list<string>}>}> $recipeUses */
        $recipeUses = [];

        foreach ($byDate as $meals) {
            $dayLabel = $this->dayLabel($meals->first());
            $pdfMeals = [];

            $sortedMeals = $meals->sortBy(fn (Meal $meal): int => MealCategory::displayIndex($meal->meal_category));

            foreach ($sortedMeals as $meal) {
                $lines = $this->ingredientLines($meal);
                $isCarriedOver = isset($carriedOver[$meal->id]);
                $pdfMeals[] = $this->pdfMeal($meal, $lines, $isCarriedOver);

                if (! $withRecipes || $meal->recipe === null || $isCarriedOver) {
                    continue;
                }

                $useSuffix = $meal->feeds_meal_id !== null
                    ? ($this->links->cookedPairFor($meal->meal_category)['use_suffix'] ?? null)
                    : null;
                $useLabel = $useSuffix !== null ? $dayLabel.' ('.$useSuffix.')' : $dayLabel;

                $groupKey = implode("\n", $lines);
                $recipeUses[$meal->recipe_id] ??= ['recipe' => $meal->recipe, 'groups' => []];
                $recipeUses[$meal->recipe_id]['groups'][$groupKey] ??= ['days' => [], 'lines' => $lines];

                if (! in_array($useLabel, $recipeUses[$meal->recipe_id]['groups'][$groupKey]['days'], true)) {
                    $recipeUses[$meal->recipe_id]['groups'][$groupKey]['days'][] = $useLabel;
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

    /**
     * Build the typed one-page grid (days as columns, meal categories as rows) of recipe names only.
     */
    public function menu(MealPlan $mealPlan): MealPlanMenu
    {
        $mealPlan->loadMissing('meals.recipe');

        $byDate = $mealPlan->meals
            ->groupBy(fn (Meal $meal): string => $meal->meal_date->format('Y-m-d'))
            ->sortKeys();

        $dayLabels = [];
        $cells = [];

        foreach ($byDate as $meals) {
            $dayLabels[] = $this->dayLabel($meals->first());

            foreach (MealCategory::cases() as $category) {
                $meal = $meals->first(fn (Meal $candidate): bool => $candidate->meal_category === $category->value);
                $cells[$category->value][] = match (true) {
                    $meal === null => '',
                    $meal->recipe === null => 'Bez posiłku',
                    default => Str::limit($meal->recipe->name, self::MENU_NAME_LIMIT),
                };
            }
        }

        return new MealPlanMenu(
            'Jadłospis '.$mealPlan->start_date->format('d.m.Y').' – '.$mealPlan->end_date->format('d.m.Y'),
            $dayLabels,
            array_map(
                fn (MealCategory $category): PdfMenuRow => new PdfMenuRow($category->label(), $category->value, $cells[$category->value] ?? array_fill(0, count($dayLabels), '')),
                MealCategory::cases(),
            ),
        );
    }

    /**
     * Render the menu-only grid as a single landscape page of PDF bytes.
     */
    public function renderMenu(MealPlan $mealPlan): string
    {
        return Pdf::loadView('pdf.meal-plan-menu', ['menu' => $this->menu($mealPlan)])
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isPhpEnabled', false)
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function menuFilename(MealPlan $mealPlan): string
    {
        return 'jadlospis-'.$mealPlan->start_date->format('Y-m-d').'-'.$mealPlan->end_date->format('Y-m-d').'-sam-jadlospis.pdf';
    }

    public function filename(MealPlan $mealPlan, bool $withRecipes = false): string
    {
        return 'jadlospis-'.$mealPlan->start_date->format('Y-m-d').'-'.$mealPlan->end_date->format('Y-m-d')
            .($withRecipes ? '-z-przepisami' : '').'.pdf';
    }

    private function dayLabel(Meal $meal): string
    {
        $day = $meal->meal_date->copy()->locale('pl');

        return Str::ucfirst($day->isoFormat('dddd')).' '.$day->format('d.m');
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
    private function pdfMeal(Meal $meal, array $ingredients, bool $isCarriedOver): PdfMeal
    {
        $recipe = $meal->recipe;
        $category = MealCategory::from($meal->meal_category);

        if ($recipe === null) {
            return new PdfMeal($category->label(), $category->value, 'Bez posiłku', false, [], true);
        }

        $marker = match (true) {
            $isCarriedOver => $this->links->carriedPairFor($meal->meal_category)['carried_label'] ?? null,
            $meal->feeds_meal_id !== null => $this->links->cookedPairFor($meal->meal_category)['cooked_label'] ?? null,
            default => null,
        };

        return new PdfMeal(
            $category->label(),
            $category->value,
            $recipe->name,
            $recipe->trashed(),
            $isCarriedOver ? [] : $ingredients,
            false,
            $marker,
            $isCarriedOver,
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
