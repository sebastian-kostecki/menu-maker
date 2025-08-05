<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RecipeControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected User $user;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    public function test_authenticated_user_can_view_recipe_index(): void
    {
        Recipe::factory()->count(3)->for($this->user)->create();
        Recipe::factory()->count(2)->for($this->otherUser)->create();

        $response = $this->actingAs($this->user)->get(route('recipes.index'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->component('Recipes/Index')
                ->has('recipes.data', 3) // Only user's recipes
        );
    }

    public function test_guest_cannot_access_recipes(): void
    {
        $response = $this->get(route('recipes.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_view_own_recipe(): void
    {
        $recipe = Recipe::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->get(route('recipes.show', $recipe));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->component('Recipes/Show')
                ->where('recipe.id', $recipe->id)
        );
    }

    public function test_user_cannot_view_other_users_recipe(): void
    {
        $recipe = Recipe::factory()->for($this->otherUser)->create();

        $response = $this->actingAs($this->user)->get(route('recipes.show', $recipe));

        $response->assertStatus(403);
    }

    public function test_user_can_create_recipe(): void
    {
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
    }

    public function test_recipe_creation_requires_validation(): void
    {
        $response = $this->actingAs($this->user)->post(route('recipes.store'), []);

        $response->assertSessionHasErrors(['name', 'category', 'instructions', 'calories', 'servings']);
    }

    public function test_recipe_creation_validates_category(): void
    {
        $recipeData = [
            'name' => 'Test Recipe',
            'category' => 'invalid_category',
            'instructions' => 'Test instructions',
            'calories' => 350,
            'servings' => 2,
        ];

        $response = $this->actingAs($this->user)->post(route('recipes.store'), $recipeData);

        $response->assertSessionHasErrors(['category']);
    }

    public function test_user_can_update_own_recipe(): void
    {
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
    }

    public function test_user_cannot_update_other_users_recipe(): void
    {
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
    }

    public function test_user_can_delete_own_recipe(): void
    {
        $recipe = Recipe::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->delete(route('recipes.destroy', $recipe));

        $response->assertRedirect(route('recipes.index'));
        $response->assertSessionHas('success', 'Recipe deleted successfully.');

        $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
    }

    public function test_user_cannot_delete_other_users_recipe(): void
    {
        $recipe = Recipe::factory()->for($this->otherUser)->create();

        $response = $this->actingAs($this->user)->delete(route('recipes.destroy', $recipe));

        $response->assertStatus(403);
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'deleted_at' => null]);
    }

    public function test_recipes_index_can_be_filtered_by_search(): void
    {
        Recipe::factory()->for($this->user)->create(['name' => 'Chocolate Cake']);
        Recipe::factory()->for($this->user)->create(['name' => 'Vanilla Ice Cream']);
        Recipe::factory()->for($this->user)->create(['name' => 'Strawberry Smoothie']);

        $response = $this->actingAs($this->user)->get(route('recipes.index', ['search' => 'chocolate']));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->has('recipes.data', 1)
        );
    }

    public function test_recipes_index_can_be_filtered_by_category(): void
    {
        Recipe::factory()->for($this->user)->breakfast()->count(2)->create();
        Recipe::factory()->for($this->user)->dinner()->count(3)->create();

        $response = $this->actingAs($this->user)->get(route('recipes.index', ['category' => 'breakfast']));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->has('recipes.data', 2)
        );
    }

    public function test_recipes_index_can_be_sorted(): void
    {
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
    }
}
