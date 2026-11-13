<?php

declare(strict_types=1);

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function attachIngredient(Recipe $recipe, Ingredient $ingredient): void
{
    RecipeIngredient::query()->forceCreate([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 1,
        'unit_id' => Unit::where('code', 'g')->value('id'),
    ]);
}

it('seeds exactly the five units with their factors', function (): void {
    $units = Unit::query()->pluck('conversion_factor_to_base', 'code')->map(fn ($v) => (float) $v)->all();

    expect($units)->toEqualCanonicalizing(['g' => 1.0, 'kg' => 1000.0, 'ml' => 1.0, 'l' => 1000.0, 'szt' => 1.0])
        ->and(array_keys($units))->toEqualCanonicalizing(Unit::ALLOWED_CODES);
});

it('seeds the Polish catalog flagged as catalog', function (): void {
    $flour = Ingredient::where('name', 'Mąka')->first();

    expect($flour)->not->toBeNull()
        ->and($flour->is_catalog)->toBeTrue();
});

it('resolves existing names case- and whitespace-insensitively without inserting', function (): void {
    $id = Ingredient::where('name', 'Mąka')->value('id');
    $count = Ingredient::count();

    expect(Ingredient::resolveIdByName(' mąka '))->toBe($id)
        ->and(Ingredient::resolveIdByName('MĄKA'))->toBe($id)
        ->and(Ingredient::count())->toBe($count);
});

it('inserts a new name once', function (): void {
    $count = Ingredient::count();

    $first = Ingredient::resolveIdByName('  Kasza   gryczana ');
    $second = Ingredient::resolveIdByName('kasza gryczana');

    expect($second)->toBe($first)
        ->and(Ingredient::count())->toBe($count + 1)
        ->and(Ingredient::find($first)->name)->toBe('Kasza gryczana')
        ->and(Ingredient::find($first)->is_catalog)->toBeFalse();
});

it('suggests catalog and own-recipe ingredients only', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $own = Ingredient::create(['name' => 'Własny składnik']);
    $foreign = Ingredient::create(['name' => 'Cudzy składnik']);
    $deleted = Ingredient::create(['name' => 'Usunięty składnik']);

    attachIngredient(Recipe::factory()->for($user)->create(), $own);
    attachIngredient(Recipe::factory()->for($other)->create(), $foreign);
    $trashed = Recipe::factory()->for($user)->create();
    attachIngredient($trashed, $deleted);
    $trashed->delete();

    $names = Ingredient::suggestedFor($user->id)->pluck('name');

    expect($names)->toContain('Mąka', 'Własny składnik')
        ->and($names)->not->toContain('Cudzy składnik', 'Usunięty składnik');
});
