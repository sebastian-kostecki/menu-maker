<?php

declare(strict_types=1);

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts quantity to decimal:2 on RecipeIngredient', function (): void {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    $ingredient = Ingredient::create(['name' => 'Sugar']);
    $unit = Unit::create(['code' => 'g', 'conversion_factor_to_base' => 1]);

    // Create pivot row directly in pivot table
    $pivot = RecipeIngredient::query()->forceCreate([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => '12.3456',
        'unit_id' => $unit->id,
    ]);

    // Reload to ensure cast from DB
    $fresh = RecipeIngredient::query()->where([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
    ])->first();

    expect($fresh)->not->toBeNull();
    expect((string) $fresh->quantity)->toBe('12.35');
});
