<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeImport\StructuredRecipeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function structuredRecipe(array $overrides = []): array
{
    return [
        'name' => 'Zupa pomidorowa',
        'category' => 'supper',
        'calories' => 800,
        'servings' => 4,
        'instructions' => 'Ugotuj.',
        'ingredients' => [['name' => 'Pomidory', 'quantity' => 500, 'unit' => 'g']],
        ...$overrides,
    ];
}

test('calories and quantities are rounded half-up to two decimals', function () {
    $recipe = app(StructuredRecipeImporter::class)->import(User::factory()->create(), structuredRecipe([
        'calories' => 100.005,
        'ingredients' => [
            ['name' => 'Pomidory', 'quantity' => 0.125, 'unit' => 'kg'],
            ['name' => 'Bulion', 'quantity' => '1.5e1', 'unit' => 'ml'],
        ],
    ]));

    expect($recipe->calories)->toBe('100.01')
        ->and($recipe->recipeIngredients()->orderBy('id')->pluck('quantity')->all())->toBe(['0.13', '15.00']);
});

test('the recipe and ingredient names are whitespace-normalized', function () {
    $recipe = app(StructuredRecipeImporter::class)->import(User::factory()->create(), structuredRecipe([
        'name' => "  Zupa \n  pomidorowa ",
        'ingredients' => [['name' => ' Passata   pomidorowa ', 'quantity' => 500, 'unit' => 'ml']],
    ]));

    expect($recipe->name)->toBe('Zupa pomidorowa')
        ->and($recipe->ingredients()->value('name'))->toBe('Passata pomidorowa');
});

test('the source is appended to the instructions only when given', function () {
    $user = User::factory()->create();
    $importer = app(StructuredRecipeImporter::class);

    $plain = $importer->import($user, structuredRecipe());
    $sourced = $importer->import($user, structuredRecipe(['name' => 'Inna zupa', 'source_url' => 'https://example.com/zupa']));

    expect($plain->instructions)->toBe('Ugotuj.')
        ->and($sourced->instructions)->toBe("Ugotuj.\n\nŹródło: https://example.com/zupa");
});

test('a duplicate name is a validation error on name', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user)->create(['name' => 'Zupa pomidorowa']);

    try {
        app(StructuredRecipeImporter::class)->import($user, structuredRecipe(['name' => 'zupa POMIDOROWA']));
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKeys(['name']);
    }

    expect(Recipe::query()->count())->toBe(1);
});
