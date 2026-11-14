<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealIngredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\IngredientScaling\MealIngredientScaler;
use App\Services\IngredientScaling\ScalingOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function scalerPlan(User $user): MealPlan
{
    return MealPlan::factory()->for($user)->done()->create([
        'start_date' => '2026-11-16',
        'end_date' => '2026-11-22',
    ]);
}

/**
 * The worked example: woman 40 (2400) + man 40 (3000) + child 7 (1800) = 7200 kcal/day.
 */
function addWorkedExampleFamily(User $user): void
{
    FamilyMember::factory()->for($user)->create(['first_name' => 'Anna', 'gender' => 'female', 'birth_date' => '1986-11-16', 'weight_kg' => '65.0', 'height_cm' => '165.0']);
    FamilyMember::factory()->for($user)->create(['first_name' => 'Jan', 'gender' => 'male', 'birth_date' => '1986-11-16', 'weight_kg' => '75.0', 'height_cm' => '180.0']);
    FamilyMember::factory()->for($user)->child()->create(['first_name' => 'Ola', 'gender' => 'female', 'birth_date' => '2019-11-16']);
}

function addMealOf(MealPlan $plan, Recipe $recipe, string $date = '2026-11-16'): Meal
{
    return Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => $recipe->id,
        'meal_date' => $date,
        'meal_category' => $recipe->category,
    ]);
}

it('scales a meal by family kcal for that meal over recipe kcal (worked example, factor 2)', function (): void {
    $user = User::factory()->create();
    addWorkedExampleFamily($user);
    $recipe = Recipe::factory()->supper()->for($user)->create(['calories' => '1440']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $recipe);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    $row = $meal->ingredients()->sole();
    expect($outcome->status)->toBe(ScalingOutcome::STATUS_COMPUTED)
        ->and($outcome->unscaledRecipes)->toBe([])
        ->and($outcome->membersWithoutBody)->toBe([])
        ->and($row->recipe_quantity)->toBe('100.00')
        ->and($row->computed_quantity)->toBe('200.00')
        ->and($row->quantity)->toBe('200.00')
        ->and($row->source)->toBe(MealIngredient::SOURCE_COMPUTED);
});

it('keeps the recipe amounts and reports as_written when the owner has no family', function (): void {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->supper()->for($user)->create(['calories' => '1440']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $recipe);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    $row = $meal->ingredients()->sole();
    expect($outcome->status)->toBe(ScalingOutcome::STATUS_AS_WRITTEN)
        ->and($row->quantity)->toBe('100.00')
        ->and($row->source)->toBe(MealIngredient::SOURCE_AS_WRITTEN);
});

it('keeps a 0-kcal recipe as written and lists it once, in Polish order', function (): void {
    $user = User::factory()->create();
    addWorkedExampleFamily($user);
    $zero = Recipe::factory()->supper()->for($user)->create(['name' => 'Żurek', 'calories' => '0']);
    $other = Recipe::factory()->breakfast()->for($user)->create(['name' => 'Łazanki', 'calories' => '0']);
    attachIngredient($zero, 'Mąka', '100', 'g');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $zero);
    addMealOf($plan, $zero, '2026-11-17');
    addMealOf($plan, $other, '2026-11-18');

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    $row = $meal->ingredients()->sole();
    expect($outcome->status)->toBe(ScalingOutcome::STATUS_COMPUTED)
        ->and($outcome->unscaledRecipes)->toBe(['Łazanki', 'Żurek'])
        ->and($row->quantity)->toBe('100.00')
        ->and($row->source)->toBe(MealIngredient::SOURCE_AS_WRITTEN);
});

it('names adults without weight or height in membersWithoutBody', function (): void {
    $user = User::factory()->create();
    FamilyMember::factory()->for($user)->create(['first_name' => 'Marek', 'gender' => 'male', 'birth_date' => '1986-11-16', 'weight_kg' => null, 'height_cm' => null]);
    FamilyMember::factory()->for($user)->child()->create(['first_name' => 'Ola', 'birth_date' => '2019-11-16']);
    $plan = scalerPlan($user);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    expect($outcome->membersWithoutBody)->toBe(['Marek'])
        ->and($outcome->toArray())->toBe([
            'status' => 'computed',
            'unscaled_recipes' => [],
            'members_without_body' => ['Marek'],
        ]);
});

it("ignores another user's family members", function (): void {
    $user = User::factory()->create();
    addWorkedExampleFamily(User::factory()->create());
    $recipe = Recipe::factory()->supper()->for($user)->create(['calories' => '1440']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $recipe);

    $outcome = app(MealIngredientScaler::class)->scale($plan);

    expect($outcome->status)->toBe(ScalingOutcome::STATUS_AS_WRITTEN)
        ->and($meal->ingredients()->sole()->quantity)->toBe('100.00');
});

it('replaces the rows on a second run and drops them with the meals (cascade)', function (): void {
    $user = User::factory()->create();
    addWorkedExampleFamily($user);
    $recipe = Recipe::factory()->supper()->for($user)->create(['calories' => '1440']);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    attachIngredient($recipe, 'Mleko', '50', 'ml');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $recipe);
    $scaler = app(MealIngredientScaler::class);

    $scaler->scale($plan);
    $scaler->scale($plan);

    expect(MealIngredient::query()->count())->toBe(2)
        ->and($meal->ingredients()->pluck('ingredient_id')->all())->toBe([
            Ingredient::resolveIdByName('Mąka'),
            Ingredient::resolveIdByName('Mleko'),
        ]);

    $plan->meals()->delete();

    expect(MealIngredient::query()->count())->toBe(0);
});

it('backfills existing meals with their recipe amounts as written', function (): void {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->supper()->for($user)->create();
    attachIngredient($recipe, 'Mąka', '100', 'g');
    attachIngredient($recipe, 'Mleko', '250', 'ml');
    $plan = scalerPlan($user);
    $meal = addMealOf($plan, $recipe);

    $migration = require database_path('migrations/2026_11_14_111711_create_meal_ingredients_table.php');
    $migration->down();
    $migration->up();

    $rows = MealIngredient::query()->where('meal_id', $meal->id)->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('quantity')->all())->toBe(['100.00', '250.00'])
        ->and($rows->pluck('recipe_quantity')->all())->toBe(['100.00', '250.00'])
        ->and($rows->pluck('source')->unique()->all())->toBe([MealIngredient::SOURCE_AS_WRITTEN])
        ->and(DB::table('meal_ingredients')->count())->toBe(2);
});
