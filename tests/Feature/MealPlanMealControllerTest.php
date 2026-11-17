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

/**
 * A plan in the given status with three Obiady on consecutive days (Monday to Wednesday) and a spare Obiad recipe.
 *
 * @return array{0: MealPlan, 1: Meal, 2: Meal, 3: Meal, 4: Recipe}
 */
function planWithThreeObiady(User $user, string $status = 'draft'): array
{
    $recipes = Recipe::factory()->count(3)->supper()->for($user)->create();
    $plan = mealPlanWithRecipes($user, $status, ...$recipes);
    [$monday, $tuesday, $wednesday] = $plan->meals()->orderBy('meal_date')->get()->all();

    return [$plan, $monday, $tuesday, $wednesday, Recipe::factory()->supper()->for($user)->create()];
}

/**
 * Link $to to $from through the endpoint.
 */
function linkMeals(User $user, MealPlan $plan, Meal $from, Meal $to): void
{
    test()->actingAs($user)->patchJson(
        route('meal-plans.meals.update', [$plan, $to]),
        ['from_meal_id' => $from->id],
    )->assertOk();
}

it("owner links Tuesday's Obiad to Monday's", function (): void {
    [$plan, $monday, $tuesday] = planWithThreeObiady($this->user);

    $response = $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $tuesday]),
        ['from_meal_id' => $monday->id],
    );

    $response->assertOk()
        ->assertJsonPath('data.meals.0.feeds_meal_id', $tuesday->id)
        ->assertJsonPath('data.meals.0.from_meal_id', null)
        ->assertJsonPath('data.meals.1.feeds_meal_id', null)
        ->assertJsonPath('data.meals.1.from_meal_id', $monday->id);
    expect($tuesday->fresh()->recipe_id)->toBe($monday->recipe_id)
        ->and($monday->fresh()->feeds_meal_id)->toBe($tuesday->id);
});

it("linking replaces the carried-over meal's own recipe", function (): void {
    [$plan, $monday, $tuesday] = planWithThreeObiady($this->user);
    $own = $tuesday->recipe_id;

    linkMeals($this->user, $plan, $monday, $tuesday);

    expect($tuesday->fresh()->recipe_id)->not->toBe($own)
        ->and($tuesday->fresh()->recipe_id)->toBe($monday->recipe_id);
});

it('linking an already linked pair changes nothing', function (): void {
    [$plan, $monday, $tuesday] = planWithThreeObiady($this->user);
    linkMeals($this->user, $plan, $monday, $tuesday);

    linkMeals($this->user, $plan, $monday, $tuesday);

    expect($monday->fresh()->feeds_meal_id)->toBe($tuesday->id)
        ->and($tuesday->fresh()->recipe_id)->toBe($monday->recipe_id);
});

it('swapping the cooked meal empties the carried-over meal and drops the link', function (): void {
    [$plan, $monday, $tuesday, , $spare] = planWithThreeObiady($this->user);
    linkMeals($this->user, $plan, $monday, $tuesday);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $monday]),
        ['recipe_id' => $spare->id],
    )->assertOk();

    expect($monday->fresh()->recipe_id)->toBe($spare->id)
        ->and($monday->fresh()->feeds_meal_id)->toBeNull()
        ->and($tuesday->fresh()->recipe_id)->toBeNull();
});

it('emptying the cooked meal empties the carried-over meal', function (): void {
    [$plan, $monday, $tuesday] = planWithThreeObiady($this->user);
    linkMeals($this->user, $plan, $monday, $tuesday);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $monday]),
        ['recipe_id' => null],
    )->assertOk();

    expect($monday->fresh()->recipe_id)->toBeNull()
        ->and($monday->fresh()->feeds_meal_id)->toBeNull()
        ->and($tuesday->fresh()->recipe_id)->toBeNull();
});

it("picking the cooked meal's current recipe keeps the link", function (): void {
    [$plan, $monday, $tuesday] = planWithThreeObiady($this->user);
    linkMeals($this->user, $plan, $monday, $tuesday);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $monday]),
        ['recipe_id' => $monday->recipe_id],
    )->assertOk();

    expect($monday->fresh()->feeds_meal_id)->toBe($tuesday->id)
        ->and($tuesday->fresh()->recipe_id)->toBe($monday->recipe_id);
});

it("swapping or emptying the carried-over meal drops the link and keeps the cooked meal's recipe", function (bool $empty): void {
    [$plan, $monday, $tuesday, , $spare] = planWithThreeObiady($this->user);
    linkMeals($this->user, $plan, $monday, $tuesday);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $tuesday]),
        ['recipe_id' => $empty ? null : $spare->id],
    )->assertOk();

    expect($monday->fresh()->feeds_meal_id)->toBeNull()
        ->and($monday->fresh()->recipe_id)->not->toBeNull()
        ->and($tuesday->fresh()->recipe_id)->toBe($empty ? null : $spare->id);
})->with([true, false]);

it("picking the carried-over meal's own recipe from the list drops the link and keeps the dish", function (): void {
    [$plan, $monday, $tuesday] = planWithThreeObiady($this->user);
    linkMeals($this->user, $plan, $monday, $tuesday);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $tuesday]),
        ['recipe_id' => $monday->recipe_id],
    )->assertOk()
        ->assertJsonPath('data.meals.1.from_meal_id', null);

    expect($monday->fresh()->feeds_meal_id)->toBeNull()
        ->and($monday->fresh()->recipe_id)->toBe($monday->recipe_id)
        ->and($tuesday->fresh()->recipe_id)->toBe($monday->recipe_id);
});

it('linking is refused for a breakfast, a non-consecutive day, an empty previous day, and a chain', function (): void {
    [$plan, $monday, $tuesday, $wednesday] = planWithThreeObiady($this->user);
    $breakfast = Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => $this->current->id,
        'meal_date' => $tuesday->meal_date,
        'meal_category' => 'breakfast',
    ]);
    $refused = fn (Meal $to, Meal $from) => $this->actingAs($this->user)
        ->patchJson(route('meal-plans.meals.update', [$plan, $to]), ['from_meal_id' => $from->id])
        ->assertStatus(422)->assertJsonValidationErrors(['from_meal_id' => UpdateMealRequest::LINK_NOT_ALLOWED_MESSAGE]);

    $refused($breakfast, $monday);
    $refused($wednesday, $monday);

    $emptied = $monday->recipe_id;
    $monday->update(['recipe_id' => null]);
    $refused($tuesday, $monday->fresh());

    $monday->update(['recipe_id' => $emptied]);
    linkMeals($this->user, $plan, $monday, $tuesday);
    $refused($wednesday, $tuesday->fresh());

    expect($wednesday->fresh()->recipe_id)->not->toBe($monday->recipe_id);
});

/**
 * A plan with Monday's Kolacja and Tuesday's Śniadanie, plus a spare Kolacja and a spare Śniadanie recipe.
 *
 * @return array{0: MealPlan, 1: Meal, 2: Meal, 3: Recipe, 4: Recipe}
 */
function planWithKolacjaAndSniadanie(User $user, string $status = 'draft'): array
{
    $plan = mealPlanWithRecipes($user, $status, Recipe::factory()->dinner()->for($user)->create(), Recipe::factory()->breakfast()->for($user)->create());
    [$kolacja, $sniadanie] = $plan->meals()->orderBy('meal_date')->get()->all();

    return [$plan, $kolacja, $sniadanie, Recipe::factory()->dinner()->for($user)->create(), Recipe::factory()->breakfast()->for($user)->create()];
}

it("owner links Tuesday's Śniadanie to Monday's Kolacja", function (): void {
    [$plan, $kolacja, $sniadanie] = planWithKolacjaAndSniadanie($this->user);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $sniadanie]),
        ['from_meal_id' => $kolacja->id],
    )->assertOk()
        ->assertJsonPath('data.meals.0.feeds_meal_id', $sniadanie->id)
        ->assertJsonPath('data.meals.0.from_meal_id', null)
        ->assertJsonPath('data.meals.1.feeds_meal_id', null)
        ->assertJsonPath('data.meals.1.from_meal_id', $kolacja->id);

    expect($sniadanie->fresh()->recipe_id)->toBe($kolacja->recipe_id)
        ->and($sniadanie->fresh()->meal_category)->toBe('breakfast')
        ->and($kolacja->fresh()->feeds_meal_id)->toBe($sniadanie->id);
});

it('linking fills an empty Śniadanie', function (): void {
    [$plan, $kolacja, $sniadanie] = planWithKolacjaAndSniadanie($this->user);
    $sniadanie->update(['recipe_id' => null]);

    linkMeals($this->user, $plan, $kolacja, $sniadanie);

    expect($sniadanie->fresh()->recipe_id)->toBe($kolacja->recipe_id)
        ->and($kolacja->fresh()->feeds_meal_id)->toBe($sniadanie->id);
});

it('swapping or emptying the Kolacja empties the carried-over Śniadanie and drops the link', function (bool $empty): void {
    [$plan, $kolacja, $sniadanie, $spareKolacja] = planWithKolacjaAndSniadanie($this->user);
    linkMeals($this->user, $plan, $kolacja, $sniadanie);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $kolacja]),
        ['recipe_id' => $empty ? null : $spareKolacja->id],
    )->assertOk();

    expect($kolacja->fresh()->recipe_id)->toBe($empty ? null : $spareKolacja->id)
        ->and($kolacja->fresh()->feeds_meal_id)->toBeNull()
        ->and($sniadanie->fresh()->recipe_id)->toBeNull();
})->with([true, false]);

it('swapping or emptying the carried-over Śniadanie drops the link and keeps the Kolacja', function (bool $empty): void {
    [$plan, $kolacja, $sniadanie, , $spareSniadanie] = planWithKolacjaAndSniadanie($this->user);
    linkMeals($this->user, $plan, $kolacja, $sniadanie);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $sniadanie]),
        ['recipe_id' => $empty ? null : $spareSniadanie->id],
    )->assertOk();

    expect($kolacja->fresh()->feeds_meal_id)->toBeNull()
        ->and($kolacja->fresh()->recipe_id)->not->toBeNull()
        ->and($sniadanie->fresh()->recipe_id)->toBe($empty ? null : $spareSniadanie->id);
})->with([true, false]);

it('a carried-over Śniadanie cannot be given the Kolacja recipe through recipe_id', function (): void {
    [$plan, $kolacja, $sniadanie] = planWithKolacjaAndSniadanie($this->user);
    linkMeals($this->user, $plan, $kolacja, $sniadanie);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $sniadanie]),
        ['recipe_id' => $kolacja->recipe_id],
    )->assertStatus(422)->assertJsonValidationErrors(['recipe_id' => UpdateMealRequest::INVALID_RECIPE_MESSAGE]);

    expect($kolacja->fresh()->feeds_meal_id)->toBe($sniadanie->id)
        ->and($sniadanie->fresh()->recipe_id)->toBe($kolacja->recipe_id);
});

it('an Obiad link and a Kolacja link coexist on the same days', function (): void {
    [$plan, $mondayKolacja, $tuesdaySniadanie] = planWithKolacjaAndSniadanie($this->user);
    $mondayObiad = Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => Recipe::factory()->supper()->for($this->user)->create()->id,
        'meal_date' => $mondayKolacja->meal_date,
        'meal_category' => 'supper',
    ]);
    $tuesdayObiad = Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => Recipe::factory()->supper()->for($this->user)->create()->id,
        'meal_date' => $tuesdaySniadanie->meal_date,
        'meal_category' => 'supper',
    ]);

    linkMeals($this->user, $plan, $mondayObiad, $tuesdayObiad);
    linkMeals($this->user, $plan, $mondayKolacja, $tuesdaySniadanie);

    expect($mondayObiad->fresh()->feeds_meal_id)->toBe($tuesdayObiad->id)
        ->and($mondayKolacja->fresh()->feeds_meal_id)->toBe($tuesdaySniadanie->id)
        ->and($tuesdayObiad->fresh()->recipe_id)->toBe($mondayObiad->recipe_id)
        ->and($tuesdaySniadanie->fresh()->recipe_id)->toBe($mondayKolacja->recipe_id);
});

it('linking a meal of another plan is refused', function (): void {
    [$plan, , $tuesday] = planWithThreeObiady($this->user);
    [, $otherMonday] = planWithThreeObiady($this->user);

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $tuesday]),
        ['from_meal_id' => $otherMonday->id],
    )->assertStatus(422)->assertJsonValidationErrors(['from_meal_id']);

    expect($otherMonday->fresh()->feeds_meal_id)->toBeNull();
});

it('linking is refused when the plan is not a draft', function (string $status): void {
    [$plan, $monday, $tuesday] = planWithThreeObiady($this->user, $status);
    $own = $tuesday->recipe_id;

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $tuesday]),
        ['from_meal_id' => $monday->id],
    )->assertStatus(422)->assertJsonValidationErrors(['status' => UpdateMealRequest::NOT_DRAFT_MESSAGE]);

    expect($monday->fresh()->feeds_meal_id)->toBeNull()
        ->and($tuesday->fresh()->recipe_id)->toBe($own);
})->with(['pending', 'processing', 'done', 'error']);

it('a link that lost the race to an approval is refused', function (): void {
    [$plan, $monday, $tuesday] = planWithThreeObiady($this->user);

    $this->app->afterResolving(UpdateMealRequest::class, function () use ($plan): void {
        MealPlan::query()->whereKey($plan->id)->update(['status' => 'pending']);
    });

    $this->actingAs($this->user)->patchJson(
        route('meal-plans.meals.update', [$plan, $tuesday]),
        ['from_meal_id' => $monday->id],
    )->assertStatus(422)->assertJsonValidationErrors(['status']);

    expect($monday->fresh()->feeds_meal_id)->toBeNull();
});
