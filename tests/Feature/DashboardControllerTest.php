<?php

declare(strict_types=1);

use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

it('redirects a guest to login', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('counts only own non-deleted recipes and own plans of every status', function (): void {
    Recipe::factory()->count(2)->create(['user_id' => $this->user->id]);
    Recipe::factory()->create(['user_id' => $this->user->id])->delete();
    Recipe::factory()->count(4)->create(['user_id' => $this->otherUser->id]);

    foreach (['draft', 'pending', 'processing', 'done', 'error'] as $i => $status) {
        MealPlan::factory()->for($this->user)->create([
            'status' => $status,
            'start_date' => now()->addWeeks($i)->startOfDay(),
            'end_date' => now()->addWeeks($i)->addDays(6)->startOfDay(),
        ]);
    }
    MealPlan::factory()->count(3)->for($this->otherUser)->create();

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Dashboard', false)
                ->where('stats.recipes', 2)
                ->where('stats.mealPlans', 5)
        );
});

it('exposes the newest done plan by start date, ignoring other statuses and other users', function (): void {
    $older = MealPlan::factory()->for($this->user)->done()->create([
        'start_date' => '2026-06-08',
        'end_date' => '2026-06-14',
    ]);
    $newer = MealPlan::factory()->for($this->user)->done()->create([
        'start_date' => '2026-06-15',
        'end_date' => '2026-06-21',
    ]);
    // The older-dated plan was touched last: start_date must still win.
    $older->forceFill(['updated_at' => now()->addDay()])->save();

    MealPlan::factory()->for($this->user)->processing()->create([
        'start_date' => '2026-06-22',
        'end_date' => '2026-06-28',
    ]);
    MealPlan::factory()->for($this->user)->error()->create([
        'start_date' => '2026-06-29',
        'end_date' => '2026-07-05',
    ]);
    MealPlan::factory()->for($this->user)->draft()->create([
        'start_date' => '2026-07-13',
        'end_date' => '2026-07-19',
    ]);
    MealPlan::factory()->for($this->otherUser)->done()->create([
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-12',
    ]);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('latestMealPlan.id', $newer->id)
                ->where('latestMealPlan.start_date', '2026-06-15')
                ->where('latestMealPlan.end_date', '2026-06-21')
                ->where('latestMealPlan.status', 'done')
                ->where('latestMealPlan.links.pdf', route('meal-plans.pdf', $newer->id))
                ->where('latestMealPlan.links.pdf_with_recipes', route('meal-plans.pdf', [$newer->id, 'recipes' => 1]))
                ->where('latestMealPlan.links.pdf_menu', route('meal-plans.pdf', [$newer->id, 'menu' => 1]))
        );
});

it('has no latest plan when the user has plans but none are done', function (): void {
    MealPlan::factory()->for($this->user)->draft()->create();
    MealPlan::factory()->for($this->user)->pending()->create();
    MealPlan::factory()->for($this->user)->error()->create();
    MealPlan::factory()->for($this->otherUser)->done()->create();

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('latestMealPlan', null));
});

it('has no latest plan when the user has no plans', function (): void {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('latestMealPlan', null));
});

it('no longer passes a recipe shortfall', function (): void {
    Recipe::factory()->count(2)->supper()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->missing('recipeShortfall')
                ->where('stats.recipes', 2)
        );
});

it('gives a brand-new user zero stats and no plan', function (): void {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('stats.recipes', 0)
                ->where('stats.mealPlans', 0)
                ->where('latestMealPlan', null)
                ->missing('recipeShortfall')
        );
});

it('exposes the latest plan\'s meals in date and category order with their recipes', function (): void {
    $breakfast = Recipe::factory()->breakfast()->create(['user_id' => $this->user->id]);
    $dinner = Recipe::factory()->dinner()->create(['user_id' => $this->user->id]);
    $plan = mealPlanWithRecipes($this->user, 'done', $breakfast, $dinner);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('latestMealPlan.id', $plan->id)
                ->has('latestMealPlan.meals', 2)
                ->where('latestMealPlan.meals.0.meal_date', $plan->start_date->format('Y-m-d'))
                ->where('latestMealPlan.meals.0.meal_category', $breakfast->category)
                ->where('latestMealPlan.meals.0.recipe.name', $breakfast->name)
                ->has('latestMealPlan.meals.0.ingredients', 0)
        );
});

it('exposes an empty slot without a recipe and the link markers', function (): void {
    $plan = MealPlan::factory()->for($this->user)->done()->create();
    $recipe = Recipe::factory()->supper()->create(['user_id' => $this->user->id]);

    $leftover = Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => $recipe->id,
        'meal_date' => $plan->start_date->copy()->addDay(),
        'meal_category' => $recipe->category,
    ]);
    $source = Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => $recipe->id,
        'meal_date' => $plan->start_date->copy(),
        'meal_category' => $recipe->category,
        'feeds_meal_id' => $leftover->id,
    ]);
    $empty = Meal::query()->create([
        'meal_plan_id' => $plan->id,
        'recipe_id' => null,
        'meal_date' => $plan->start_date->copy()->addDays(2),
        'meal_category' => $recipe->category,
    ]);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->has('latestMealPlan.meals', 3)
                ->where('latestMealPlan.meals.0.id', $source->id)
                ->where('latestMealPlan.meals.0.feeds_meal_id', $leftover->id)
                ->where('latestMealPlan.meals.1.id', $leftover->id)
                ->where('latestMealPlan.meals.1.from_meal_id', $source->id)
                ->where('latestMealPlan.meals.2.id', $empty->id)
                ->missing('latestMealPlan.meals.2.recipe')
        );
});

it('never exposes meals of another user\'s or a non-done plan', function (): void {
    $own = mealPlanWithRecipes(
        $this->user,
        'done',
        Recipe::factory()->breakfast()->create(['user_id' => $this->user->id]),
        Recipe::factory()->dinner()->create(['user_id' => $this->user->id]),
    );
    $own->forceFill(['start_date' => '2026-06-15', 'end_date' => '2026-06-21'])->save();

    $foreign = mealPlanWithRecipes(
        $this->otherUser,
        'done',
        Recipe::factory()->breakfast()->create(['user_id' => $this->otherUser->id]),
    );
    $foreign->forceFill(['start_date' => '2026-07-06', 'end_date' => '2026-07-12'])->save();

    $draft = mealPlanWithRecipes(
        $this->user,
        'draft',
        Recipe::factory()->breakfast()->create(['user_id' => $this->user->id]),
    );
    $draft->forceFill(['start_date' => '2026-07-13', 'end_date' => '2026-07-19'])->save();

    $ownMealIds = $own->meals()->pluck('id')->all();

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(function ($page) use ($own, $ownMealIds): void {
            $page->where('latestMealPlan.id', $own->id);

            $exposed = collect($page->toArray()['props']['latestMealPlan']['meals'])->pluck('id')->sort()->values()->all();
            expect($exposed)->toBe(collect($ownMealIds)->sort()->values()->all());
        });
});
