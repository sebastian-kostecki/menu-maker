<?php

declare(strict_types=1);

use App\Mcp\Servers\RecipeServer;
use App\Mcp\Tools\AddRecipeTool;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function recipeArguments(array $overrides = []): array
{
    return [
        'name' => 'Owsianka z jabłkiem',
        'category' => 'breakfast',
        'calories' => 640,
        'servings' => 2,
        'instructions' => "Zagotuj mleko.\nDodaj płatki i jabłko.",
        'ingredients' => [
            ['name' => 'Płatki owsiane', 'quantity' => 100, 'unit' => 'g'],
            ['name' => 'Mleko', 'quantity' => 400, 'unit' => 'ml'],
            ['name' => 'Jabłko', 'quantity' => 1, 'unit' => 'szt'],
        ],
        ...$overrides,
    ];
}

test('a valid call stores the recipe for the acting user and answers with its link', function () {
    $user = User::factory()->create();
    $milk = Ingredient::query()->firstOrCreate(['name' => 'Mleko'], ['is_catalog' => true]);

    $response = RecipeServer::actingAs($user)->tool(AddRecipeTool::class, recipeArguments([
        'source_url' => 'https://example.com/owsianka',
        'ingredients' => [
            ['name' => 'Płatki owsiane', 'quantity' => 100, 'unit' => 'g'],
            ['name' => 'mleko', 'quantity' => 400, 'unit' => 'ml'],
            ['name' => 'Jabłko', 'quantity' => 1, 'unit' => 'szt'],
        ],
    ]));

    $recipe = Recipe::query()->sole();

    $response->assertOk()->assertStructuredContent([
        'recipe' => [
            'id' => $recipe->id,
            'name' => 'Owsianka z jabłkiem',
            'category' => 'breakfast',
            'category_label' => 'Śniadanie',
            'calories' => 640.0,
            'servings' => 2,
            'ingredient_count' => 3,
            'url' => route('recipes.show', $recipe),
        ],
    ]);

    expect($recipe->user_id)->toBe($user->id)
        ->and($recipe->calories)->toBe('640.00')
        ->and($recipe->instructions)->toBe("Zagotuj mleko.\nDodaj płatki i jabłko.\n\nŹródło: https://example.com/owsianka")
        ->and($recipe->recipeIngredients()->with(['ingredient', 'unit'])->get()
            ->mapWithKeys(fn ($row) => [$row->ingredient->name => [$row->quantity, $row->unit->code]])
            ->all())
        ->toEqual([
            'Płatki owsiane' => ['100.00', 'g'],
            'Mleko' => ['400.00', 'ml'],
            'Jabłko' => ['1.00', 'szt'],
        ])
        ->and($recipe->ingredients()->whereKey($milk->id)->exists())->toBeTrue()
        ->and(Ingredient::query()->whereRaw('lower(name) = ?', ['mleko'])->count())->toBe(1);
});

test('a name the user already has is refused with the existing link', function (string $stored, string $given) {
    $user = User::factory()->create();
    $existing = Recipe::factory()->for($user)->create(['name' => $stored]);

    RecipeServer::actingAs($user)->tool(AddRecipeTool::class, recipeArguments(['name' => $given]))
        ->assertHasErrors(['Masz już przepis', route('recipes.show', $existing)]);

    expect(Recipe::query()->count())->toBe(1);
})->with([
    'different case' => ['Owsianka z jabłkiem', 'OWSIANKA Z JABŁKIEM'],
    'extra whitespace in the argument' => ['Owsianka z jabłkiem', "  Owsianka   z\njabłkiem "],
    'extra whitespace in the stored name' => ['Owsianka  z jabłkiem ', 'Owsianka z jabłkiem'],
]);

test('the same name of another user or of a deleted recipe does not block', function () {
    $user = User::factory()->create();
    $other = Recipe::factory()->create(['name' => 'OWSIANKA Z JABŁKIEM', 'instructions' => 'Cudzy sekretny krok.']);
    Recipe::factory()->for($user)->create(['name' => 'Owsianka z jabłkiem'])->delete();

    RecipeServer::actingAs($user)->tool(AddRecipeTool::class, recipeArguments())
        ->assertOk()
        ->assertDontSee([$other->name, $other->instructions, route('recipes.show', $other)]);

    expect($user->recipes()->count())->toBe(1);
});

test('a duplicate refusal never points at another user\'s recipe', function () {
    $other = Recipe::factory()->create(['name' => 'OWSIANKA Z JABŁKIEM', 'instructions' => 'Cudzy sekretny krok.']);
    $user = User::factory()->create();
    $own = Recipe::factory()->for($user)->create(['name' => 'Owsianka z jabłkiem']);

    RecipeServer::actingAs($user)->tool(AddRecipeTool::class, recipeArguments())
        ->assertHasErrors([route('recipes.show', $own)])
        ->assertDontSee([$other->name, $other->instructions, route('recipes.show', $other)]);
});

test('input the recipe form would not accept is refused and nothing is stored', function (array $overrides, string $message) {
    RecipeServer::actingAs(User::factory()->create())->tool(AddRecipeTool::class, recipeArguments($overrides))
        ->assertHasErrors([$message]);

    expect(Recipe::query()->count())->toBe(0);
})->with([
    'calories below 50' => [['calories' => 49], 'co najmniej 50 kcal'],
    'calories above the column limit' => [['calories' => 1e12], 'calories'],
    'unknown category' => [['category' => 'lunch'], 'Kategoria musi być'],
    'unit word' => [['ingredients' => [['name' => 'Cukier', 'quantity' => 1, 'unit' => 'łyżka']]], 'g, kg, ml, l, szt'],
    'repeated ingredient' => [['ingredients' => [
        ['name' => 'Cukier', 'quantity' => 1, 'unit' => 'g'],
        ['name' => ' cukier ', 'quantity' => 2, 'unit' => 'g'],
    ]], 'Ten składnik już jest na liście.'],
    'no ingredients' => [['ingredients' => []], 'co najmniej jeden składnik'],
    'ftp source' => [['source_url' => 'ftp://example.com/przepis'], 'source url'],
    'javascript source' => [['source_url' => 'javascript:alert(1)'], 'source url'],
]);

test('the tool is a non-destructive write that requires user interaction', function () {
    $tool = app(AddRecipeTool::class)->toArray();

    expect($tool['name'])->toBe('add-recipe-tool')
        ->and($tool['annotations'])->toMatchArray([
            'readOnlyHint' => false,
            'destructiveHint' => false,
            'idempotentHint' => false,
        ])
        ->and($tool['_meta'])->toMatchArray(['anthropic/requiresUserInteraction' => true]);
});

test('the tool refuses a call without a signed-in user', function () {
    RecipeServer::tool(AddRecipeTool::class, recipeArguments())->assertHasErrors(['No user is signed in']);

    expect(Recipe::query()->count())->toBe(0);
});
