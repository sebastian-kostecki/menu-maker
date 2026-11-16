<?php

declare(strict_types=1);

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

it('lists exactly one shortfall message per short category', function (): void {
    Recipe::factory()->count(7)->breakfast()->create(['user_id' => $this->user->id]);
    Recipe::factory()->count(3)->supper()->create(['user_id' => $this->user->id]);
    Recipe::factory()->count(7)->dinner()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page->where('recipeShortfall', [
                'Za mało przepisów w kategorii Obiad: masz 3, potrzeba 7. Dodaj jeszcze 4.',
            ])
        );
});

it('has an empty shortfall with seven recipes in each category', function (): void {
    Recipe::factory()->count(7)->breakfast()->create(['user_id' => $this->user->id]);
    Recipe::factory()->count(7)->supper()->create(['user_id' => $this->user->id]);
    Recipe::factory()->count(7)->dinner()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page->where('recipeShortfall', [])->where('stats.recipes', 21)
        );
});

it('gives a brand-new user zero stats, no plan and three shortfall messages', function (): void {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('stats.recipes', 0)
                ->where('stats.mealPlans', 0)
                ->where('latestMealPlan', null)
                ->has('recipeShortfall', 3)
        );
});
