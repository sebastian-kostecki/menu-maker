<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class);
uses(WithFaker::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

it('authenticated user can view recipe index', function (): void {
    Recipe::factory()->count(3)->for($this->user)->create();
    Recipe::factory()->count(2)->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->get(route('recipes.index'));

    $response->assertStatus(200);
    $response->assertInertia(
        fn($page) => $page->component('Recipes/Index')
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
        fn($page) => $page->component('Recipes/Show')
            ->where('recipe.id', $recipe->id)
    );
});

it("user cannot view other user's recipe", function (): void {
    $recipe = Recipe::factory()->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->get(route('recipes.show', $recipe));

    $response->assertStatus(403);
});

it('user can create recipe', function (): void {
    $recipeData = [
        'name' => 'Test Recipe',
        'category' => 'breakfast',
        'instructions' => 'Test instructions for the recipe.',
        'calories' => 350,
        'servings' => 2,
    ];

    $response = $this->actingAs($this->user)->post(route('recipes.store'), $recipeData);

    $response->assertRedirect(route('recipes.index'));
    $response->assertSessionHas('success', 'Recipe created successfully.');

    $this->assertDatabaseHas('recipes', [
        'name' => 'Test Recipe',
        'user_id' => $this->user->id,
        'category' => 'breakfast',
    ]);
});

it('recipe creation requires validation', function (): void {
    $response = $this->actingAs($this->user)->post(route('recipes.store'), []);

    $response->assertSessionHasErrors(['name', 'category', 'instructions', 'calories', 'servings']);
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

    $updateData = [
        'name' => 'Updated Recipe Name',
        'category' => 'dinner',
        'instructions' => 'Updated instructions',
        'calories' => 500,
        'servings' => 4,
    ];

    $response = $this->actingAs($this->user)->put(route('recipes.update', $recipe), $updateData);

    $response->assertRedirect(route('recipes.show', $recipe));
    $response->assertSessionHas('success', 'Recipe updated successfully.');

    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
        'name' => 'Updated Recipe Name',
        'category' => 'dinner',
    ]);
});

it("user cannot update other user's recipe", function (): void {
    $recipe = Recipe::factory()->for($this->otherUser)->create();

    $updateData = [
        'name' => 'Hacked Recipe',
        'category' => 'dinner',
        'instructions' => 'Hacked instructions',
        'calories' => 500,
        'servings' => 4,
    ];

    $response = $this->actingAs($this->user)->put(route('recipes.update', $recipe), $updateData);

    $response->assertStatus(403);
});

it('user can delete own recipe', function (): void {
    $recipe = Recipe::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user)->delete(route('recipes.destroy', $recipe));

    $response->assertRedirect(route('recipes.index'));
    $response->assertSessionHas('success', 'Recipe deleted successfully.');

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
        fn($page) => $page->has('recipes.data', 1)
    );
});

it('recipes index can be filtered by category', function (): void {
    Recipe::factory()->for($this->user)->breakfast()->count(2)->create();
    Recipe::factory()->for($this->user)->dinner()->count(3)->create();

    $response = $this->actingAs($this->user)->get(route('recipes.index', ['category' => 'breakfast']));

    $response->assertStatus(200);
    $response->assertInertia(
        fn($page) => $page->has('recipes.data', 2)
    );
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
        fn($page) => $page->where('recipes.data.0.name', 'A Recipe')
    );
});
