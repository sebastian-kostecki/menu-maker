<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Unit;
use App\Models\User;
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
