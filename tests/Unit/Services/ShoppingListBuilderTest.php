<?php

declare(strict_types=1);

use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\ShoppingList\QuantityLine;
use App\Services\ShoppingList\ShoppingListBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const SHOPPING_FACTORS = ['g' => '1.0000', 'kg' => '1000.0000', 'ml' => '1.0000', 'l' => '1000.0000', 'szt' => '1.0000'];

/**
 * @param  list<array{0: string, 1: string}>  $entries  Pairs of [quantity, unit code].
 * @return list<string>
 */
function displaysFor(array $entries, string $name = 'Mąka', int $id = 1): array
{
    $lines = array_map(
        fn (array $entry): QuantityLine => new QuantityLine($id, $name, $entry[1], SHOPPING_FACTORS[$entry[1]], $entry[0]),
        $entries,
    );

    return array_map(fn ($item) => $item->display, (new ShoppingListBuilder)->build($lines));
}

it('sums quantities within a unit', function (): void {
    expect(displaysFor([['200', 'g'], ['300', 'g']]))->toBe(['500 g']);
});

it('normalizes to the base unit before choosing the display unit', function (): void {
    expect(displaysFor([['0.30', 'kg'], ['900', 'g']]))->toBe(['1,2 kg'])
        ->and(displaysFor([['0.50', 'kg']]))->toBe(['500 g']);
});

it('keeps exactly 1000 in the base unit', function (): void {
    expect(displaysFor([['1000', 'g']]))->toBe(['1000 g'])
        ->and(displaysFor([['1000', 'ml']]))->toBe(['1000 ml']);
});

it('converts just above the threshold', function (): void {
    expect(displaysFor([['1000.01', 'g']]))->toBe(['1 kg'])
        ->and(displaysFor([['1001', 'ml']]))->toBe(['1 l']);
});

it('rounds half-up exactly to 2 decimals', function (): void {
    expect(displaysFor([['1234', 'g']]))->toBe(['1,23 kg'])
        ->and(displaysFor([['1005', 'g']]))->toBe(['1,01 kg'])
        ->and(displaysFor([['1004.99', 'g']]))->toBe(['1 kg']);
});

it('converts volume with 2 decimals', function (): void {
    expect(displaysFor([['1050', 'ml']]))->toBe(['1,05 l'])
        ->and(displaysFor([['0.25', 'l'], ['0.80', 'l']]))->toBe(['1,05 l']);
});

it('never converts pieces', function (): void {
    expect(displaysFor([['1500', 'szt']]))->toBe(['1500 szt.'])
        ->and(displaysFor([['2.50', 'szt']]))->toBe(['2,5 szt.']);
});

it('keeps different unit groups of one ingredient as separate rows, mass first', function (): void {
    expect(displaysFor([['2', 'szt'], ['100', 'g']], 'Jajka'))->toBe(['100 g', '2 szt.'])
        ->and(displaysFor([['200', 'ml'], ['100', 'g']], 'Mleko'))->toBe(['100 g', '200 ml']);
});

it('sorts names in Polish order', function (): void {
    $lines = [];
    foreach (['Ziemniaki', 'Łosoś', 'Masło', 'Cebula', 'Ćwikła'] as $index => $name) {
        $lines[] = new QuantityLine($index + 1, $name, 'g', '1.0000', '1');
    }

    $names = array_map(fn ($item) => $item->name, (new ShoppingListBuilder)->build($lines));

    expect($names)->toBe(['Cebula', 'Ćwikła', 'Łosoś', 'Masło', 'Ziemniaki']);
});

it('returns an empty list for no lines', function (): void {
    expect((new ShoppingListBuilder)->build([]))->toBe([]);
});

it('exposes the rounded quantity as a dot-decimal string without trailing zeros', function (): void {
    $build = fn (string $quantity, string $unit): string => (new ShoppingListBuilder)
        ->build([new QuantityLine(1, 'Mąka', $unit, SHOPPING_FACTORS[$unit], $quantity)])[0]->quantity;

    expect($build('1234', 'g'))->toBe('1.23')
        ->and($build('500', 'g'))->toBe('500')
        ->and($build('2.50', 'szt'))->toBe('2.5');
});

function planWithRecipes(User $user, Recipe ...$recipes): MealPlan
{
    $plan = MealPlan::factory()->for($user)->done()->create();

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

it('sums ingredients across the plan meals', function (): void {
    $user = User::factory()->create();
    $first = Recipe::factory()->for($user)->create();
    $second = Recipe::factory()->for($user)->create();
    attachIngredient($first, 'Mąka', '600', 'g');
    attachIngredient($second, 'Mąka', '600', 'g');

    $list = (new ShoppingListBuilder)->forMealPlan(planWithRecipes($user, $first, $second));

    expect($list->items)->toHaveCount(1)
        ->and($list->items[0]->display)->toBe('1,2 kg')
        ->and($list->recipesWithoutIngredients)->toBe([]);
});

it('counts the ingredients of a soft-deleted recipe', function (): void {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    attachIngredient($recipe, 'Mleko', '250', 'ml');
    $plan = planWithRecipes($user, $recipe);
    $recipe->delete();

    $list = (new ShoppingListBuilder)->forMealPlan($plan);

    expect($list->items)->toHaveCount(1)
        ->and($list->items[0]->display)->toBe('250 ml');
});

it('lists recipes without ingredients once, in Polish order, without adding rows', function (): void {
    $user = User::factory()->create();
    $zupa = Recipe::factory()->for($user)->create(['name' => 'Zupa']);
    $lazanki = Recipe::factory()->for($user)->create(['name' => 'Łazanki']);
    $plan = planWithRecipes($user, $zupa, $lazanki, $zupa);

    $list = (new ShoppingListBuilder)->forMealPlan($plan);

    expect($list->items)->toBe([])
        ->and($list->recipesWithoutIngredients)->toBe(['Łazanki', 'Zupa']);
});

it('does not leak meals of another plan of the same user', function (): void {
    $user = User::factory()->create();
    $inPlan = Recipe::factory()->for($user)->create();
    $elsewhere = Recipe::factory()->for($user)->create();
    attachIngredient($inPlan, 'Mąka', '100', 'g');
    attachIngredient($elsewhere, 'Cukier', '50', 'g');
    $plan = planWithRecipes($user, $inPlan);
    planWithRecipes($user, $elsewhere);

    $list = (new ShoppingListBuilder)->forMealPlan($plan);

    expect(array_map(fn ($item) => $item->name, $list->items))->toBe(['Mąka']);
});
