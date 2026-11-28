<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Unit;
use App\Models\User;
use App\Services\IngredientScaling\MealIngredientScaler;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
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

    return snapshotMealIngredients($plan);
}

/**
 * Give the user a man (180 cm, 75 kg) and a woman (165 cm, 65 kg) aged 42: 3000 + 2400 = 5400 kcal a day,
 * so a 810-kcal breakfast or Kolacja and a 1080-kcal Obiad scale by exactly 2.0.
 */
function seedTwoAdultFamily(User $user): void
{
    FamilyMember::factory()->for($user)->create(['first_name' => 'Adam', 'gender' => 'male', 'birth_date' => '1984-06-01', 'height_cm' => '180', 'weight_kg' => '75']);
    FamilyMember::factory()->for($user)->create(['first_name' => 'Ewa', 'gender' => 'female', 'birth_date' => '1984-06-01', 'height_cm' => '165', 'weight_kg' => '65']);
}

/**
 * Write the plan's per-meal ingredient snapshot with the real scaler: as written when the owner has no family,
 * scaled (and rounded by the fake refiner) when they do.
 */
function snapshotMealIngredients(MealPlan $plan): MealPlan
{
    app(MealIngredientScaler::class)->scale($plan);

    return $plan->unsetRelation('meals');
}

/**
 * Extract the text of PDF bytes, with whitespace collapsed (approximate: cells may wrap or be split by tabs and newlines).
 */
function pdfText(string $bytes): string
{
    return preg_replace('/\s+/u', ' ', (new Parser)->parseContent($bytes)->getText()) ?? '';
}

/**
 * An access token and its refresh token, as an OAuth client of the user would hold them.
 *
 * @return array{access: Token, refresh: RefreshToken}
 */
function oauthGrantFor(User $user): array
{
    $client = app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Claude', ['http://localhost/callback'], false);
    $access = Passport::token()->forceFill([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->getKey(),
        'scopes' => ['mcp:use'],
        'revoked' => false,
    ]);
    $access->save();
    $refresh = Passport::refreshToken()->forceFill([
        'id' => Str::random(80),
        'access_token_id' => $access->id,
        'revoked' => false,
    ]);
    $refresh->save();

    return ['access' => $access, 'refresh' => $refresh];
}
