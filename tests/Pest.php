<?php

declare(strict_types=1);

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Unit;
use App\Models\User;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/**
 * This file configures Pest for the Laravel application test suite.
 */
uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Give the user the given number of recipes in every meal category.
 */
function seedFullWeekOfRecipes(User $user, int $perCategory = 7): void
{
    Recipe::factory()->count($perCategory)->breakfast()->for($user)->create();
    Recipe::factory()->count($perCategory)->supper()->for($user)->create();
    Recipe::factory()->count($perCategory)->dinner()->for($user)->create();
}

/**
 * Attach an ingredient (resolved by name) with a quantity and unit code to the recipe.
 */
function attachIngredient(Recipe $recipe, string $name, string $quantity, string $unitCode): void
{
    RecipeIngredient::query()->forceCreate([
        'recipe_id' => $recipe->id,
        'ingredient_id' => Ingredient::resolveIdByName($name),
        'quantity' => $quantity,
        'unit_id' => Unit::query()->where('code', $unitCode)->value('id'),
    ]);
}

/**
 * Create a plan in the given status (a MealPlanFactory state) with one meal per recipe on consecutive days.
 */
function mealPlanWithRecipes(User $user, string $status, Recipe ...$recipes): MealPlan
{
    $plan = MealPlan::factory()->for($user)->{$status}()->create();

    foreach ($recipes as $index => $recipe) {
        Meal::query()->create([
            'meal_plan_id' => $plan->id,
            'recipe_id' => $recipe->id,
            'meal_date' => $plan->start_date->copy()->addDays($index),
            'meal_category' => $recipe->category,
        ]);
    }

    return $plan;
}

/**
 * Extract the text of PDF bytes, with whitespace collapsed (approximate: cells may wrap or be split by tabs and newlines).
 */
function pdfText(string $bytes): string
{
    return preg_replace('/\s+/u', ' ', (new Parser)->parseContent($bytes)->getText()) ?? '';
}
