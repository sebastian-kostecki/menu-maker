<?php

declare(strict_types=1);

use App\Http\Requests\UpdateMealRequest;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->current = Recipe::factory()->breakfast()->for($this->user)->create();
    $this->candidate = Recipe::factory()->breakfast()->for($this->user)->create();
});

/**
 * Create a plan in the given status with one breakfast meal of the given recipe.
 */
function planWithBreakfast(User $user, string $status, Recipe $recipe): array
{
    $plan = mealPlanWithRecipes($user, $status, $recipe);

    return [$plan, $plan->meals()->firstOrFail()];
}

it('owner swaps a draft meal to another recipe of the same category', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);

    $response = $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => $this->candidate->id],
    );

    $response->assertOk()->assertJsonPath('data.meals.0.recipe.id', $this->candidate->id);
    expect($meal->fresh()->recipe_id)->toBe($this->candidate->id);
});

it('swap to a recipe already used that week is allowed and leaves both meals', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);
    $other = Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => $this->candidate->id,
        'meal_date' => $plan->start_date->copy()->addDay(),
        'meal_category' => 'breakfast',
    ]);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => $this->candidate->id],
    )->assertOk();

    expect($meal->fresh()->recipe_id)->toBe($this->candidate->id)
        ->and($other->fresh()->recipe_id)->toBe($this->candidate->id)
        ->and($plan->meals()->count())->toBe(2);
});

it('swapping to the current recipe is a valid no-op', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => $this->current->id],
    )->assertOk();

    expect($meal->fresh()->recipe_id)->toBe($this->current->id);
});

it('swap is refused for a recipe of another category', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);
    $dinner = Recipe::factory()->dinner()->for($this->user)->create();

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => $dinner->id],
    )->assertStatus(422)->assertJsonValidationErrors(['recipe_id' => UpdateMealRequest::INVALID_RECIPE_MESSAGE]);

    expect($meal->fresh()->recipe_id)->toBe($this->current->id);
});

it('swap is refused for a deleted recipe', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);
    $this->candidate->delete();

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => $this->candidate->id],
    )->assertStatus(422)->assertJsonValidationErrors(['recipe_id']);

    expect($meal->fresh()->recipe_id)->toBe($this->current->id);
});

it("swap is refused for another user's recipe", function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);
    $foreign = Recipe::factory()->breakfast()->for($this->otherUser)->create();

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => $foreign->id],
    )->assertStatus(422)->assertJsonValidationErrors(['recipe_id']);

    expect($meal->fresh()->recipe_id)->toBe($this->current->id);
});

it('a missing recipe_id key is refused', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);

    $this->actingAs($this->user)->patchJson(route('meal-plans.meals.update', [$plan, $meal]), [])
        ->assertStatus(422)->assertJsonValidationErrors(['recipe_id']);
});

it('owner empties a draft meal with recipe_id null', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);

    $response = $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => null],
    );

    $response->assertJsonMissingPath('data.meals.0.recipe.id');
    $response->assertJsonMissingPath('data.meals.0.recipe');
    $response->assertOk();
    expect($meal->fresh()->recipe_id)->toBeNull()
        ->and($plan->meals()->count())->toBe(1);
});

it('owner fills an empty meal', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);
    $meal->update(['recipe_id' => null]);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => $this->candidate->id],
    )->assertOk()->assertJsonPath('data.meals.0.recipe.id', $this->candidate->id);

    expect($meal->fresh()->recipe_id)->toBe($this->candidate->id);
});

it('emptying is refused when the plan is not a draft', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'done', $this->current);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => null],
    )->assertStatus(422)->assertJsonValidationErrors(['status' => UpdateMealRequest::NOT_DRAFT_MESSAGE]);

    expect($meal->fresh()->recipe_id)->toBe($this->current->id);
});

it('swap is refused when the plan is not a draft', function (string $status): void {
    [$plan, $meal] = planWithBreakfast($this->user, $status, $this->current);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => $this->candidate->id],
    )->assertStatus(422)->assertJsonValidationErrors(['status' => UpdateMealRequest::NOT_DRAFT_MESSAGE]);

    expect($meal->fresh()->recipe_id)->toBe($this->current->id);
})->with(['pending', 'processing', 'done', 'error']);

it('a swap that lost the race to an approval is refused', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);

    $this->app->afterResolving(UpdateMealRequest::class, function () use ($plan): void {
        MealPlan::query()->whereKey($plan->id)->update(['status' => 'pending']);
    });

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => $this->candidate->id],
    )->assertStatus(422)->assertJsonValidationErrors(['status']);

    expect($meal->fresh()->recipe_id)->toBe($this->current->id);
});

it('non-owner gets 403', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);

    $this->actingAs($this->otherUser)->patchJson(
        route('meal-plans.meals.update', [$plan, $meal]),
        ['recipe_id' => $this->candidate->id],
    )->assertForbidden();

    expect($meal->fresh()->recipe_id)->toBe($this->current->id);
});

it('a meal of another plan in the URL gets 404', function (): void {
    [$plan] = planWithBreakfast($this->user, 'draft', $this->current);
    [, $foreignMeal] = planWithBreakfast($this->user, 'draft', $this->current);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $foreignMeal]),
        ['recipe_id' => $this->candidate->id],
    )->assertNotFound();

    expect($foreignMeal->fresh()->recipe_id)->toBe($this->current->id);
});

it('guest is redirected to login', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);

    $this->patch(route('meal-plans.meals.update', [$plan, $meal]), ['recipe_id' => $this->candidate->id])
        ->assertRedirect(route('login'));
});

it('Inertia swap redirects back', function (): void {
    [$plan, $meal] = planWithBreakfast($this->user, 'draft', $this->current);

    $this->actingAs($this->user)
        ->from(route('meal-plans.show', $plan))
        ->patch(route('meal-plans.meals.update', [$plan, $meal]), ['recipe_id' => $this->candidate->id])
        ->assertRedirect(route('meal-plans.show', $plan));

    expect($meal->fresh()->recipe_id)->toBe($this->candidate->id);
});
