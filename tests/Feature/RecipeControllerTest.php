<?php

declare(strict_types=1);

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class);
uses(WithFaker::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->gramsId = (int) Unit::where('code', 'g')->value('id');
});

/**
 * @return array<string, mixed>
 */
function recipePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Recipe',
        'category' => 'breakfast',
        'instructions' => 'Test instructions for the recipe.',
        'calories' => 350,
        'servings' => 2,
        'ingredients' => [['name' => 'Mąka', 'quantity' => 200, 'unit_id' => (int) Unit::where('code', 'g')->value('id')]],
    ], $overrides);
}

it('authenticated user can view recipe index', function (): void {
    Recipe::factory()->count(3)->for($this->user)->create();
    Recipe::factory()->count(2)->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->get(route('recipes.index'));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->component('Recipes/Index')
            ->has('recipes.data', 3)
    );
});

it('guest cannot access recipes', function (): void {
    $response = $this->get(route('recipes.index'));

    $response->assertRedirect(route('login'));
});

it('user can view own recipe', function (): void {
    $recipe = Recipe::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user)->get(route('recipes.show', $recipe));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->component('Recipes/Show')
            ->where('recipe.id', $recipe->id)
    );
});

it("user cannot view other user's recipe", function (): void {
    $recipe = Recipe::factory()->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->get(route('recipes.show', $recipe));

    $response->assertStatus(403);
});

it('user can create recipe', function (): void {
    $response = $this->actingAs($this->user)->post(route('recipes.store'), recipePayload());

    $response->assertRedirect(route('recipes.index'));
    $response->assertSessionHas('success', 'Przepis został dodany.');

    $this->assertDatabaseHas('recipes', [
        'name' => 'Test Recipe',
        'user_id' => $this->user->id,
        'category' => 'breakfast',
    ]);
});

it('recipe creation requires validation', function (): void {
    $response = $this->actingAs($this->user)->post(route('recipes.store'), []);

    $response->assertSessionHasErrors(['name', 'category', 'instructions', 'calories', 'servings', 'ingredients']);
});

it('store links a catalog ingredient by name without creating a new row', function (): void {
    $catalogId = Ingredient::where('name', 'Mąka')->value('id');
    $before = Ingredient::count();

    $this->actingAs($this->user)->post(route('recipes.store'), recipePayload([
        'ingredients' => [['name' => ' mąka ', 'quantity' => 200, 'unit_id' => $this->gramsId]],
    ]))->assertRedirect(route('recipes.index'));

    expect(Ingredient::count())->toBe($before);
    $this->assertDatabaseHas('recipe_ingredients', ['ingredient_id' => $catalogId, 'unit_id' => $this->gramsId]);
});

it('store creates one custom ingredient for a new name', function (): void {
    $before = Ingredient::count();

    $this->actingAs($this->user)->post(route('recipes.store'), recipePayload([
        'ingredients' => [['name' => 'Czarnuszka', 'quantity' => 5, 'unit_id' => $this->gramsId]],
    ]))->assertRedirect(route('recipes.index'));

    expect(Ingredient::count())->toBe($before + 1);
    $this->assertDatabaseHas('ingredients', ['name' => 'Czarnuszka', 'is_catalog' => false]);
});

it('recipe creation validates category', function (): void {
    $recipeData = [
        'name' => 'Test Recipe',
        'category' => 'invalid_category',
        'instructions' => 'Test instructions',
        'calories' => 350,
        'servings' => 2,
    ];

    $response = $this->actingAs($this->user)->post(route('recipes.store'), $recipeData);

    $response->assertSessionHasErrors(['category']);
});

it('user can update own recipe', function (): void {
    $recipe = Recipe::factory()->for($this->user)->create();

    $updateData = recipePayload([
        'name' => 'Updated Recipe Name',
        'category' => 'dinner',
        'instructions' => 'Updated instructions',
        'calories' => 500,
        'servings' => 4,
    ]);

    $response = $this->actingAs($this->user)->put(route('recipes.update', $recipe), $updateData);

    $response->assertRedirect(route('recipes.show', $recipe));
    $response->assertSessionHas('success', 'Przepis został zaktualizowany.');

    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
        'name' => 'Updated Recipe Name',
        'category' => 'dinner',
    ]);
});

it('update without ingredients is rejected and leaves the recipe untouched', function (): void {
    $recipe = Recipe::factory()->for($this->user)->create(['name' => 'Original']);

    $response = $this->actingAs($this->user)->put(
        route('recipes.update', $recipe),
        collect(recipePayload(['name' => 'Changed']))->except('ingredients')->all(),
    );

    $response->assertSessionHasErrors('ingredients');
    $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'name' => 'Original']);
    expect($recipe->ingredients()->count())->toBe(0);
});

it('update replaces the ingredient set', function (): void {
    $recipe = Recipe::factory()->for($this->user)->create();
    $this->actingAs($this->user)->put(route('recipes.update', $recipe), recipePayload());

    $this->actingAs($this->user)->put(route('recipes.update', $recipe), recipePayload([
        'ingredients' => [['name' => 'Cukier', 'quantity' => 50, 'unit_id' => $this->gramsId]],
    ]))->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->ingredients()->pluck('name')->all())->toBe(['Cukier']);
});

it("user cannot update other user's recipe", function (): void {
    $recipe = Recipe::factory()->for($this->otherUser)->create();

    $updateData = recipePayload(['name' => 'Hacked Recipe']);

    $response = $this->actingAs($this->user)->put(route('recipes.update', $recipe), $updateData);

    $response->assertStatus(403);
});

it('user can delete own recipe', function (): void {
    $recipe = Recipe::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user)->delete(route('recipes.destroy', $recipe));

    $response->assertRedirect(route('recipes.index'));
    $response->assertSessionHas('success', 'Przepis został usunięty.');

    $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
});

it("user cannot delete other user's recipe", function (): void {
    $recipe = Recipe::factory()->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->delete(route('recipes.destroy', $recipe));

    $response->assertStatus(403);
    $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'deleted_at' => null]);
});

it('recipes index can be filtered by search', function (): void {
    Recipe::factory()->for($this->user)->create(['name' => 'Chocolate Cake']);
    Recipe::factory()->for($this->user)->create(['name' => 'Vanilla Ice Cream']);
    Recipe::factory()->for($this->user)->create(['name' => 'Strawberry Smoothie']);

    $response = $this->actingAs($this->user)->get(route('recipes.index', ['search' => 'chocolate']));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->has('recipes.data', 1)
    );
});

it('recipes search ignores case and diacritics', function (string $term): void {
    Recipe::factory()->for($this->user)->create(['name' => 'Żurek']);
    Recipe::factory()->for($this->user)->create(['name' => 'Pierogi']);

    $response = $this->actingAs($this->user)->get(route('recipes.index', ['search' => $term]));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->has('recipes.data', 1)->where('recipes.data.0.name', 'Żurek')
    );
})->with(['zurek', 'ŻUREK', 'żurek']);

it('recipes search matches instructions only terms', function (): void {
    Recipe::factory()->for($this->user)->create(['name' => 'Soup', 'instructions' => 'Dodaj śmietanę']);
    Recipe::factory()->for($this->user)->create(['name' => 'Bread', 'instructions' => 'Bake']);

    $response = $this->actingAs($this->user)->get(route('recipes.index', ['search' => 'smietane']));

    $response->assertInertia(
        fn ($page) => $page->has('recipes.data', 1)->where('recipes.data.0.name', 'Soup')
    );
});

it("recipes search never returns another user's recipes", function (): void {
    Recipe::factory()->for($this->otherUser)->create(['name' => 'Żurek']);

    $response = $this->actingAs($this->user)->get(route('recipes.index', ['search' => 'zurek']));

    $response->assertInertia(fn ($page) => $page->has('recipes.data', 0));
});

it('recipes index can be filtered by category', function (): void {
    Recipe::factory()->for($this->user)->breakfast()->count(2)->create();
    Recipe::factory()->for($this->user)->dinner()->count(3)->create();

    $response = $this->actingAs($this->user)->get(route('recipes.index', ['category' => 'breakfast']));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->has('recipes.data', 2)
    );
});

it('recipe form and index expose Polish category labels in display order', function (): void {
    $expected = [
        ['value' => 'breakfast', 'label' => 'Śniadanie'],
        ['value' => 'supper', 'label' => 'Obiad'],
        ['value' => 'dinner', 'label' => 'Kolacja'],
    ];

    $this->actingAs($this->user)->get(route('recipes.index'))
        ->assertInertia(fn ($page) => $page->where('categories', $expected));
    $this->actingAs($this->user)->get(route('recipes.create'))
        ->assertInertia(fn ($page) => $page->where('categories', $expected));
});

it('create and edit expose the catalog and own custom ingredients but not those of other users', function (): void {
    $own = Recipe::factory()->for($this->user)->create();
    $own->ingredients()->attach(Ingredient::resolveIdByName('Moja przyprawa'), ['quantity' => 1, 'unit_id' => $this->gramsId]);
    $foreign = Recipe::factory()->for($this->otherUser)->create();
    $foreign->ingredients()->attach(Ingredient::resolveIdByName('Cudza przyprawa'), ['quantity' => 1, 'unit_id' => $this->gramsId]);

    $assertNames = fn ($page) => $page
        ->where('ingredients', fn ($items) => collect($items)->pluck('name')->contains('Mąka')
            && collect($items)->pluck('name')->contains('Moja przyprawa')
            && ! collect($items)->pluck('name')->contains('Cudza przyprawa'))
        ->where('units', fn ($units) => collect($units)->pluck('code')->all() === ['g', 'kg', 'ml', 'l', 'szt']);

    $this->actingAs($this->user)->get(route('recipes.create'))->assertInertia($assertNames);
    $this->actingAs($this->user)->get(route('recipes.edit', $own))->assertInertia($assertNames);
});

it('recipes index can be sorted', function (): void {
    Recipe::factory()->for($this->user)->create(['name' => 'Z Recipe']);
    Recipe::factory()->for($this->user)->create(['name' => 'A Recipe']);

    $response = $this->actingAs($this->user)->get(route('recipes.index', [
        'sort' => 'name',
        'direction' => 'asc',
    ]));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->where('recipes.data.0.name', 'A Recipe')
    );
});
