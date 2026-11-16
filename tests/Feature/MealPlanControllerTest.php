<?php

declare(strict_types=1);

use App\Http\Requests\ApproveMealPlanRequest;
use App\Http\Requests\GenerateMealPlanRequest;
use App\Http\Requests\RegenerateMealPlanRequest;
use App\Http\Requests\ReturnMealPlanToDraftRequest;
use App\Jobs\GenerateMealPlanJob;
use App\Models\Meal;
use App\Models\MealIngredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\MealPlanGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);
uses(WithFaker::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

it('authenticated user can view meal plan index', function (): void {
    MealPlan::factory()->count(3)->for($this->user)->create();
    MealPlan::factory()->count(2)->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->get(route('meal-plans.index'));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->component('MealPlans/Index', false)
            ->has('mealPlans.data', 3)
    );
});

it('index exposes single-valued pagination meta and links', function (): void {
    MealPlan::factory()->count(20)->for($this->user)->create();

    $response = $this->actingAs($this->user)
        ->get(route('meal-plans.index', ['perPage' => 5]));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->has('mealPlans.data', 5)
            ->where('mealPlans.meta.current_page', 1)
            ->where('mealPlans.meta.last_page', 4)
            ->where('mealPlans.meta.total', 20)
            ->where('mealPlans.meta.per_page', 5)
            ->where('mealPlans.links.next', fn ($next) => is_string($next))
            ->has('mealPlans.meta', 6)
            ->hasAll(['mealPlans.meta.current_page', 'mealPlans.meta.last_page', 'mealPlans.meta.per_page', 'mealPlans.meta.total', 'mealPlans.meta.from', 'mealPlans.meta.to'])
    );
});

it('guest cannot access meal plans', function (): void {
    $response = $this->get(route('meal-plans.index'));

    $response->assertRedirect(route('login'));
});

it('index can filter by status', function (): void {
    MealPlan::factory()->pending()->for($this->user)->create();
    MealPlan::factory()->done()->count(2)->for($this->user)->create();

    $response = $this->actingAs($this->user)
        ->get(route('meal-plans.index', ['filter' => ['status' => 'done']]));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->has('mealPlans.data', 2)
            ->where('filters.filter.status', 'done')
    );
});

it('user can view own meal plan', function (): void {
    $mealPlan = MealPlan::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user)->get(route('meal-plans.show', $mealPlan));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->component('MealPlans/Show', false)
            ->has('mealPlan')
            ->where('mealPlan.id', $mealPlan->id)
    );
});

it("user cannot view other user's meal plan", function (): void {
    $mealPlan = MealPlan::factory()->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->get(route('meal-plans.show', $mealPlan));

    $response->assertStatus(403);
});

it('requires valid start date to generate meal plan', function (): void {
    $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
        'start_date' => now()->subDay()->format('Y-m-d'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['start_date']);
});

it('prevents duplicate start dates per user', function (): void {
    $startDate = now()->addDays(7)->format('Y-m-d');

    MealPlan::factory()->for($this->user)->create(['start_date' => $startDate]);

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
        'start_date' => $startDate,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['start_date']);
});

it('requires the regenerate flag to redraw', function (): void {
    $mealPlan = MealPlan::factory()->draft()->for($this->user)->create();

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['regenerate']);
});

it("user cannot redraw other user's meal plan", function (): void {
    $mealPlan = MealPlan::factory()->draft()->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), [
        'regenerate' => true,
    ]);

    $response->assertStatus(403);
});

it('store creates a draft with 21 drawn meals and queues nothing', function (): void {
    Queue::fake();
    seedFullWeekOfRecipes($this->user);
    $startDate = now()->addDays(7)->format('Y-m-d');

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), ['start_date' => $startDate]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['message', 'data' => ['id', 'start_date', 'end_date', 'status', 'links' => ['self']]]);
    $response->assertJsonPath('data.status', 'draft');

    $mealPlan = MealPlan::where('user_id', $this->user->id)->firstOrFail();
    expect($mealPlan->status)->toBe('draft')
        ->and($mealPlan->meals()->count())->toBe(21);
    foreach (['breakfast', 'supper', 'dinner'] as $category) {
        expect($mealPlan->meals()->where('meal_category', $category)->count())->toBe(7);
    }
    Queue::assertNothingPushed();
});

it('store is not limited to 5 per hour and leaves the approval limiter untouched', function (): void {
    seedFullWeekOfRecipes($this->user);
    RateLimiter::clear('generate-meal-plan:'.$this->user->id);

    for ($i = 1; $i <= 6; $i++) {
        $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
            'start_date' => now()->addDays($i * 7)->format('Y-m-d'),
        ])->assertStatus(201);
    }

    expect(MealPlan::where('user_id', $this->user->id)->count())->toBe(6)
        ->and(RateLimiter::attempts('generate-meal-plan:'.$this->user->id))->toBe(0);
});

it('store and redraw share a 30 per minute throttle that leaves client error reports alone', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($this->user)->postJson(route('meal-plans.store'), [])->assertStatus(422);
    }

    $draft = MealPlan::factory()->draft()->for($this->user)->create();

    $this->actingAs($this->user)->postJson(route('meal-plans.store'), [])->assertStatus(429);
    $this->actingAs($this->user)->putJson(route('meal-plans.update', $draft), ['regenerate' => true])->assertStatus(429);
    $this->actingAs($this->user)->postJson(route('client-errors.store'), [
        'message' => 'Cannot read properties of undefined',
        'stack' => 'TypeError: at Foo.vue:10',
        'url' => '/meal-plans',
        'source' => 'vue',
    ])->assertNoContent();
});

it('store turns a run-time shortfall into a recipes error and creates no plan', function (): void {
    seedFullWeekOfRecipes($this->user);

    // Recipes vanish after the request passed validation.
    $this->app->afterResolving(GenerateMealPlanRequest::class, function (): void {
        Recipe::query()->where('user_id', $this->user->id)->byCategory('dinner')->delete();
    });

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
        'start_date' => now()->addDays(7)->format('Y-m-d'),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['recipes']);
    expect($response->json('errors.recipes.0'))->toContain('Kolacja');
    $this->assertDatabaseCount('meal_plans', 0);
    $this->assertDatabaseCount('meals', 0);
});

it('redraw replaces the meals of a draft', function (): void {
    Queue::fake();
    seedFullWeekOfRecipes($this->user, 12);
    $mealPlan = MealPlan::factory()->draft()->for($this->user)->create();
    app(MealPlanGenerator::class)->generate($mealPlan);
    $before = $mealPlan->meals()->pluck('id');

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), ['regenerate' => true]);

    $response->assertOk()->assertJsonPath('data.status', 'draft');
    expect($mealPlan->fresh()->status)->toBe('draft')
        ->and($mealPlan->meals()->count())->toBe(21)
        ->and($mealPlan->meals()->whereIn('id', $before)->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('redraw is refused for every non-draft status', function (string $status): void {
    Queue::fake();
    seedFullWeekOfRecipes($this->user);
    $mealPlan = MealPlan::factory()->{$status}()->for($this->user)->create();

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), ['regenerate' => true]);

    $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    expect($mealPlan->fresh()->status)->toBe($status);
    Queue::assertNothingPushed();
})->with(['done', 'error', 'pending', 'processing']);

it('redraw keeps the meals on a shortfall', function (): void {
    seedFullWeekOfRecipes($this->user);
    $mealPlan = MealPlan::factory()->draft()->for($this->user)->create();
    app(MealPlanGenerator::class)->generate($mealPlan);
    $before = $mealPlan->meals()->pluck('id')->sort()->values()->all();

    Recipe::query()->where('user_id', $this->user->id)->byCategory('dinner')->limit(2)->get()->each->delete();

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), ['regenerate' => true]);

    $response->assertStatus(422)->assertJsonValidationErrors(['recipes']);
    expect($mealPlan->meals()->pluck('id')->sort()->values()->all())->toBe($before);
});

it('redraw that lost the race to an approval is refused', function (): void {
    Queue::fake();
    seedFullWeekOfRecipes($this->user);
    $mealPlan = MealPlan::factory()->draft()->for($this->user)->create();

    $this->app->afterResolving(RegenerateMealPlanRequest::class, function () use ($mealPlan): void {
        MealPlan::query()->whereKey($mealPlan->id)->update(['status' => 'pending']);
    });

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), ['regenerate' => true]);

    $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    expect($mealPlan->meals()->count())->toBe(0);
});

it('redirects to the plan view after an Inertia generate and redraw', function (): void {
    seedFullWeekOfRecipes($this->user);

    $response = $this->actingAs($this->user)->post(route('meal-plans.store'), [
        'start_date' => now()->addDays(7)->format('Y-m-d'),
    ]);

    $mealPlan = MealPlan::where('user_id', $this->user->id)->firstOrFail();
    $response->assertRedirect(route('meal-plans.show', $mealPlan));

    $this->actingAs($this->user)->put(route('meal-plans.update', $mealPlan), ['regenerate' => true])
        ->assertRedirect(route('meal-plans.show', $mealPlan));
});

it('approve moves a draft to pending and queues the job', function (): void {
    Queue::fake();
    $recipe = Recipe::factory()->for($this->user)->create();
    $mealPlan = mealPlanWithRecipes($this->user, 'draft', $recipe);
    $mealPlan->update(['generation_meta' => ['stale' => true]]);

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.approve', $mealPlan));

    $response->assertStatus(202)->assertJsonPath('data.status', 'pending');
    expect($mealPlan->fresh()->status)->toBe('pending')
        ->and($mealPlan->fresh()->generation_meta)->toBeNull();
    Queue::assertPushed(GenerateMealPlanJob::class, fn ($job) => $job->mealPlan->id === $mealPlan->id);
});

it('approve is refused for every non-draft status', function (string $status): void {
    Queue::fake();
    $recipe = Recipe::factory()->for($this->user)->create();
    $mealPlan = mealPlanWithRecipes($this->user, $status, $recipe);

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.approve', $mealPlan));

    $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    expect($mealPlan->fresh()->status)->toBe($status);
    Queue::assertNothingPushed();
})->with(['pending', 'processing', 'done', 'error']);

it('approve is refused for an empty draft', function (): void {
    Queue::fake();
    $mealPlan = MealPlan::factory()->draft()->for($this->user)->create();

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.approve', $mealPlan));

    $response->assertStatus(422)->assertJsonValidationErrors(['meals']);
    expect($mealPlan->fresh()->status)->toBe('draft');
    Queue::assertNothingPushed();
});

it('approve is limited to 5 per hour', function (): void {
    Queue::fake();
    RateLimiter::clear('generate-meal-plan:'.$this->user->id);
    $recipe = Recipe::factory()->for($this->user)->create();
    $mealPlan = mealPlanWithRecipes($this->user, 'draft', $recipe);

    for ($i = 0; $i < 5; $i++) {
        MealPlan::whereKey($mealPlan->id)->update(['status' => 'draft']);
        $this->actingAs($this->user)->postJson(route('meal-plans.approve', $mealPlan))->assertStatus(202);
    }

    MealPlan::whereKey($mealPlan->id)->update(['status' => 'draft']);
    $this->actingAs($this->user)->postJson(route('meal-plans.approve', $mealPlan))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['rate_limit']);
    Queue::assertPushed(GenerateMealPlanJob::class, 5);
});

it('a refused approval still counts towards the hourly limit', function (): void {
    Queue::fake();
    RateLimiter::clear('generate-meal-plan:'.$this->user->id);
    $emptyDraft = MealPlan::factory()->draft()->for($this->user)->create();

    $this->actingAs($this->user)->postJson(route('meal-plans.approve', $emptyDraft))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['meals']);

    expect(RateLimiter::attempts('generate-meal-plan:'.$this->user->id))->toBe(1);
    Queue::assertNothingPushed();
});

it('approve that lost the race is refused', function (): void {
    Queue::fake();
    $recipe = Recipe::factory()->for($this->user)->create();
    $mealPlan = mealPlanWithRecipes($this->user, 'draft', $recipe);

    $this->app->afterResolving(ApproveMealPlanRequest::class, function () use ($mealPlan): void {
        MealPlan::query()->whereKey($mealPlan->id)->update(['status' => 'pending']);
    });

    $this->actingAs($this->user)->postJson(route('meal-plans.approve', $mealPlan))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
    Queue::assertNothingPushed();
});

it('non-owner cannot approve', function (): void {
    Queue::fake();
    $recipe = Recipe::factory()->for($this->otherUser)->create();
    $mealPlan = mealPlanWithRecipes($this->otherUser, 'draft', $recipe);

    $this->actingAs($this->user)->postJson(route('meal-plans.approve', $mealPlan))->assertForbidden();
    expect($mealPlan->fresh()->status)->toBe('draft');
    Queue::assertNothingPushed();
});

it('Inertia approve redirects to show', function (): void {
    Queue::fake();
    $recipe = Recipe::factory()->for($this->user)->create();
    $mealPlan = mealPlanWithRecipes($this->user, 'draft', $recipe);

    $this->actingAs($this->user)->post(route('meal-plans.approve', $mealPlan))
        ->assertRedirect(route('meal-plans.show', $mealPlan));
});

it('back to draft keeps the meals, drops the ingredients and the scaling meta', function (string $status): void {
    $recipe = Recipe::factory()->for($this->user)->create();
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $mealPlan = mealPlanWithRecipes($this->user, $status, $recipe);
    $mealPlan->update(['generation_meta' => ['version' => 'scaled-v1']]);
    $mealIds = $mealPlan->meals()->pluck('id')->all();
    expect(MealIngredient::whereIn('meal_id', $mealIds)->count())->toBe(1);

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.draft', $mealPlan));

    $response->assertOk()->assertJsonPath('data.status', 'draft');
    expect($mealPlan->fresh()->status)->toBe('draft')
        ->and($mealPlan->fresh()->generation_meta)->toBeNull()
        ->and($mealPlan->meals()->pluck('id')->all())->toBe($mealIds)
        ->and(MealIngredient::whereIn('meal_id', $mealIds)->count())->toBe(0);
})->with(['done', 'error']);

it('back to draft is refused for draft, pending and processing', function (string $status): void {
    $mealPlan = MealPlan::factory()->{$status}()->for($this->user)->create();

    $this->actingAs($this->user)->postJson(route('meal-plans.draft', $mealPlan))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
    expect($mealPlan->fresh()->status)->toBe($status);
})->with(['draft', 'pending', 'processing']);

it('non-owner cannot send a plan back to draft', function (): void {
    $mealPlan = MealPlan::factory()->done()->for($this->otherUser)->create();

    $this->actingAs($this->user)->postJson(route('meal-plans.draft', $mealPlan))->assertForbidden();
    expect($mealPlan->fresh()->status)->toBe('done');
});

it('back to draft that lost the race is refused', function (): void {
    $mealPlan = MealPlan::factory()->done()->for($this->user)->create();

    $this->app->afterResolving(ReturnMealPlanToDraftRequest::class, function () use ($mealPlan): void {
        MealPlan::query()->whereKey($mealPlan->id)->update(['status' => 'pending']);
    });

    $this->actingAs($this->user)->postJson(route('meal-plans.draft', $mealPlan))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('Inertia back to draft redirects to show', function (): void {
    $mealPlan = MealPlan::factory()->done()->for($this->user)->create();

    $this->actingAs($this->user)->post(route('meal-plans.draft', $mealPlan))
        ->assertRedirect(route('meal-plans.show', $mealPlan));
});

it('a done plan that went back to draft and was approved again shows scaled ingredients', function (): void {
    seedTwoAdultFamily($this->user);
    $recipe = Recipe::factory()->breakfast()->for($this->user)->create(['calories' => 810]);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $plan = mealPlanWithRecipes($this->user, 'done', $recipe);

    $this->actingAs($this->user)->post(route('meal-plans.draft', $plan))->assertRedirect();
    expect(MealIngredient::whereIn('meal_id', $plan->meals()->pluck('id'))->count())->toBe(0);

    // The queue is sync in tests, so approval runs the job (fake refiner) inline.
    $this->actingAs($this->user)->post(route('meal-plans.approve', $plan))->assertRedirect();

    expect($plan->fresh()->status)->toBe('done');
    $this->actingAs($this->user)->get(route('meal-plans.show', $plan))
        ->assertInertia(
            fn ($page) => $page->has('mealPlan.meals.0.ingredients', 1)
                ->where('mealPlan.meals.0.ingredients.0.display', '200 g')
        );
});

it('a draft gets no shopping list and no PDF link', function (): void {
    $recipe = Recipe::factory()->for($this->user)->create();
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $plan = mealPlanWithRecipes($this->user, 'draft', $recipe);

    $this->actingAs($this->user)->get(route('meal-plans.show', $plan))
        ->assertInertia(
            fn ($page) => $page->where('shoppingList', null)
                ->where('mealPlan.links.pdf', null)
        );
    $this->actingAs($this->user)->get(route('meal-plans.pdf', $plan))->assertNotFound();
});

it('index offers the draft status filter', function (): void {
    MealPlan::factory()->draft()->for($this->user)->create();
    MealPlan::factory()->done()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('meal-plans.index', ['filter' => ['status' => 'draft']]))
        ->assertInertia(
            fn ($page) => $page->has('mealPlans.data', 1)
                ->where('statuses.0', ['value' => 'draft', 'label' => 'Szkic'])
        );
});

it('user can delete own meal plan', function (): void {
    $mealPlan = MealPlan::factory()->done()->for($this->user)->create();

    $response = $this->actingAs($this->user)->deleteJson(route('meal-plans.destroy', $mealPlan));

    $response->assertStatus(204);

    $this->assertDatabaseMissing('meal_plans', ['id' => $mealPlan->id]);
});

it('user cannot delete processing meal plan', function (): void {
    $mealPlan = MealPlan::factory()->processing()->for($this->user)->create();

    $response = $this->actingAs($this->user)->deleteJson(route('meal-plans.destroy', $mealPlan));

    $response->assertStatus(403);
    $this->assertDatabaseHas('meal_plans', ['id' => $mealPlan->id]);
});

it("user cannot delete other user's meal plan", function (): void {
    $mealPlan = MealPlan::factory()->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->deleteJson(route('meal-plans.destroy', $mealPlan));

    $response->assertStatus(403);
    $this->assertDatabaseHas('meal_plans', ['id' => $mealPlan->id]);
});

it('responds with JSON when expected', function (): void {
    $mealPlan = MealPlan::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user)
        ->withHeaders(['Accept' => 'application/json'])
        ->get(route('meal-plans.index'));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['*' => ['id', 'start_date', 'end_date', 'status']],
        'meta',
        'links',
    ]);

    $response = $this->actingAs($this->user)
        ->withHeaders(['Accept' => 'application/json'])
        ->get(route('meal-plans.show', $mealPlan));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['id', 'start_date', 'end_date', 'status', 'links'],
    ]);
});

it('refuses generation when a category is short', function (): void {
    Queue::fake();
    Recipe::factory()->count(7)->breakfast()->for($this->user)->create();
    Recipe::factory()->count(7)->supper()->for($this->user)->create();
    Recipe::factory()->count(4)->dinner()->for($this->user)->create();

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
        'start_date' => now()->addDays(7)->format('Y-m-d'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['recipes']);
    expect($response->json('errors.recipes.0'))->toContain('Kolacja')->toContain('Dodaj jeszcze 3');
    $this->assertDatabaseCount('meal_plans', 0);
    Queue::assertNothingPushed();
});

it('does not touch the approval rate limit on a shortfall', function (): void {
    RateLimiter::clear('generate-meal-plan:'.$this->user->id);

    $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
        'start_date' => now()->addDays(7)->format('Y-m-d'),
    ])->assertJsonValidationErrors(['recipes']);

    expect(RateLimiter::attempts('generate-meal-plan:'.$this->user->id))->toBe(0);
});

it('redirects to the index after an Inertia delete', function (): void {
    $mealPlan = MealPlan::factory()->done()->for($this->user)->create();

    $response = $this->actingAs($this->user)->delete(route('meal-plans.destroy', $mealPlan));

    $response->assertRedirect(route('meal-plans.index'));
    $this->assertDatabaseMissing('meal_plans', ['id' => $mealPlan->id]);
});

it('turns a concurrent duplicate start date into a validation error', function (): void {
    Queue::fake();
    seedFullWeekOfRecipes($this->user);
    $startDate = now()->addDays(7)->format('Y-m-d');

    // A second tab creates the same week after this request passed validation.
    $this->app->afterResolving(GenerateMealPlanRequest::class, function () use ($startDate): void {
        MealPlan::factory()->for($this->user)->create([
            'start_date' => $startDate,
            'end_date' => now()->addDays(13)->format('Y-m-d'),
        ]);
    });

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
        'start_date' => $startDate,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_date' => GenerateMealPlanRequest::DUPLICATE_START_DATE_MESSAGE]);
    expect(MealPlan::where('user_id', $this->user->id)->count())->toBe(1);
    Queue::assertNothingPushed();
});

it('passes the summed shopping list for a done plan', function (): void {
    $first = Recipe::factory()->for($this->user)->create();
    $second = Recipe::factory()->for($this->user)->create();
    $empty = Recipe::factory()->for($this->user)->create(['name' => 'Owsianka']);
    attachIngredient($first, 'Mąka', '600', 'g');
    attachIngredient($second, 'Mąka', '600', 'g');
    $plan = mealPlanWithRecipes($this->user, 'done', $first, $second, $empty);

    $this->actingAs($this->user)->get(route('meal-plans.show', $plan))
        ->assertInertia(
            fn ($page) => $page->component('MealPlans/Show', false)
                ->has('shoppingList.items', 1)
                ->where('shoppingList.items.0.name', 'Mąka')
                ->where('shoppingList.items.0.quantity', '1.2')
                ->where('shoppingList.items.0.unit', 'kg')
                ->where('shoppingList.items.0.display', '1,2 kg')
                ->where('shoppingList.recipes_without_ingredients', ['Owsianka'])
        );
});

it('passes each meal its stored ingredients with the display string and source', function (): void {
    $recipe = Recipe::factory()->for($this->user)->create();
    attachIngredient($recipe, 'Mąka', '250', 'g');
    $plan = mealPlanWithRecipes($this->user, 'done', $recipe);

    $this->actingAs($this->user)->get(route('meal-plans.show', $plan))
        ->assertInertia(
            fn ($page) => $page->component('MealPlans/Show', false)
                ->has('mealPlan.meals.0.ingredients', 1)
                ->where('mealPlan.meals.0.ingredients.0.name', 'Mąka')
                ->where('mealPlan.meals.0.ingredients.0.quantity', '250.00')
                ->where('mealPlan.meals.0.ingredients.0.unit.code', 'g')
                ->where('mealPlan.meals.0.ingredients.0.display', '250 g')
                ->where('mealPlan.meals.0.ingredients.0.source', 'as_written')
                ->missing('mealPlan.meals.0.recipe.ingredients')
        );
});

it('passes each meal its amounts scaled to the family', function (): void {
    seedTwoAdultFamily($this->user);
    $recipe = Recipe::factory()->breakfast()->for($this->user)->create(['calories' => 810]);
    attachIngredient($recipe, 'Mąka', '100', 'g');
    attachIngredient($recipe, 'Jajka', '1', 'szt');
    $plan = mealPlanWithRecipes($this->user, 'done', $recipe);

    $this->actingAs($this->user)->get(route('meal-plans.show', $plan))
        ->assertInertia(
            fn ($page) => $page->component('MealPlans/Show', false)
                ->has('mealPlan.meals.0.ingredients', 2)
                ->where('mealPlan.meals.0.ingredients.0.display', '200 g')
                ->where('mealPlan.meals.0.ingredients.0.source', 'ai')
                ->where('mealPlan.meals.0.ingredients.1.display', '2 szt.')
                ->where('shoppingList.items.0.display', '2 szt.')
                ->where('shoppingList.items.1.display', '200 g')
        );
});

it('passes the scaling notices of a done plan', function (): void {
    $recipe = Recipe::factory()->for($this->user)->create();
    $plan = mealPlanWithRecipes($this->user, 'done', $recipe);
    $plan->update(['generation_meta' => ['version' => 'scaled-v1', 'scaling' => [
        'status' => 'computed',
        'unscaled_recipes' => ['Zupa'],
        'members_without_body' => [],
    ]]]);

    $this->actingAs($this->user)->get(route('meal-plans.show', $plan))
        ->assertInertia(
            fn ($page) => $page->component('MealPlans/Show', false)
                ->where('mealPlan.scaling.status', 'computed')
                ->where('mealPlan.scaling.notices', [
                    'Nie udało się dopracować ilości przez AI — pokazujemy ilości przeliczone automatycznie.',
                    'Przepisy z brakującą lub zbyt niską kalorycznością nie zostały przeliczone: Zupa.',
                ])
        );
});

it('passes no shopping list while the plan is generating', function (string $status): void {
    $recipe = Recipe::factory()->for($this->user)->create();
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $plan = mealPlanWithRecipes($this->user, $status, $recipe);

    $this->actingAs($this->user)->get(route('meal-plans.show', $plan))
        ->assertInertia(fn ($page) => $page->where('shoppingList', null));
})->with(['pending', 'processing']);

it('returns the shopping list on a partial reload', function (): void {
    $recipe = Recipe::factory()->for($this->user)->create();
    attachIngredient($recipe, 'Mleko', '250', 'ml');
    $plan = mealPlanWithRecipes($this->user, 'done', $recipe);

    $this->actingAs($this->user)->get(route('meal-plans.show', $plan))
        ->assertInertia(
            fn ($page) => $page->reloadOnly(
                ['mealPlan', 'shoppingList'],
                fn ($reload) => $reload->where('mealPlan.id', $plan->id)
                    ->where('shoppingList.items.0.display', '250 ml')
            )
        );
});

it('owner gets the rendered PDF inline and uncached', function (): void {
    $recipe = Recipe::factory()->for($this->user)->dinner()->create(['name' => 'Łosoś pieczony']);
    attachIngredient($recipe, 'Mąka', '600', 'g');
    $mealPlan = mealPlanWithRecipes($this->user, 'done', $recipe);

    $response = $this->actingAs($this->user)->get(route('meal-plans.pdf', $mealPlan));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader(
        'Content-Disposition',
        'inline; filename="jadlospis-'.$mealPlan->start_date->format('Y-m-d').'-'.$mealPlan->end_date->format('Y-m-d').'.pdf"'
    );
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $bytes = $response->getContent();
    expect($bytes)->toStartWith('%PDF-');

    $text = pdfText($bytes);
    expect(strpos($text, 'Łosoś pieczony'))->toBeInt()
        ->and(strpos($text, 'Łosoś pieczony'))->toBeLessThan(strpos($text, 'Lista zakupów'));
});

it("user cannot download another user's PDF whatever its status", function (string $status): void {
    $mealPlan = MealPlan::factory()->{$status}()->for($this->otherUser)->create();

    $this->actingAs($this->user)->get(route('meal-plans.pdf', $mealPlan))->assertForbidden();
})->with(['done', 'pending']);

it('PDF is not available until the plan is done', function (string $status): void {
    $mealPlan = MealPlan::factory()->{$status}()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('meal-plans.pdf', $mealPlan))->assertNotFound();
})->with(['pending', 'processing', 'error']);

it('guest is redirected to login from the PDF route', function (): void {
    $mealPlan = MealPlan::factory()->done()->for($this->user)->create();

    $this->get(route('meal-plans.pdf', $mealPlan))->assertRedirect(route('login'));
});

it('PDF reflects the current meals after regeneration', function (): void {
    $old = Recipe::factory()->for($this->user)->dinner()->create(['name' => 'Stary Żurek']);
    $mealPlan = mealPlanWithRecipes($this->user, 'done', $old);

    $new = Recipe::factory()->for($this->user)->dinner()->create(['name' => 'Nowy Bigos']);
    $mealPlan->meals()->delete();
    Meal::query()->create([
        'meal_plan_id' => $mealPlan->id,
        'recipe_id' => $new->id,
        'meal_date' => $mealPlan->start_date,
        'meal_category' => $new->category,
    ]);

    $text = pdfText($this->actingAs($this->user)->get(route('meal-plans.pdf', $mealPlan))->getContent());

    expect($text)->toContain('Nowy Bigos')->not->toContain('Stary Żurek');
});

it('exposes the PDF link only for a done plan and no stored path', function (): void {
    $done = MealPlan::factory()->done()->for($this->user)->create();
    $pending = MealPlan::factory()->pending()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('meal-plans.show', $done))->assertInertia(
        fn ($page) => $page->where('mealPlan.links.pdf', route('meal-plans.pdf', $done))
            ->missing('mealPlan.pdf_path')
    );
    $this->actingAs($this->user)->get(route('meal-plans.show', $pending))->assertInertia(
        fn ($page) => $page->where('mealPlan.links.pdf', null)
    );
});

it('exposes the PDF link on the list only for done plans', function (): void {
    $done = MealPlan::factory()->done()->for($this->user)->create();
    $pending = MealPlan::factory()->pending()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('meal-plans.index'))->assertInertia(
        function ($page) use ($done, $pending): void {
            $links = collect($page->toArray()['props']['mealPlans']['data'])->pluck('links.pdf', 'id');

            expect($links[$done->id])->toBe(route('meal-plans.pdf', $done))
                ->and($links[$pending->id])->toBeNull();
        }
    );
});

it('a draft passes recipe options per category without deleted recipes', function (): void {
    $plan = MealPlan::factory()->draft()->for($this->user)->create();
    Recipe::factory()->breakfast()->for($this->user)->create(['name' => 'Zupa', 'calories' => 300]);
    Recipe::factory()->breakfast()->for($this->user)->create(['name' => 'Awokado', 'calories' => 250]);
    Recipe::factory()->dinner()->for($this->user)->create(['name' => 'Kolacja X', 'calories' => 410]);
    Recipe::factory()->breakfast()->for($this->user)->create(['name' => 'Usunięty'])->delete();
    Recipe::factory()->breakfast()->for($this->otherUser)->create(['name' => 'Cudzy']);

    $this->actingAs($this->user)->get(route('meal-plans.show', $plan))
        ->assertInertia(
            fn ($page) => $page->component('MealPlans/Show', false)
                ->has('recipeOptions.breakfast', 2)
                ->where('recipeOptions.breakfast.0.name', 'Awokado')
                ->where('recipeOptions.breakfast.0.calories', '250.00')
                ->where('recipeOptions.breakfast.1.name', 'Zupa')
                ->has('recipeOptions.supper', 0)
                ->has('recipeOptions.dinner', 1)
                ->where('recipeOptions.dinner.0.calories', '410.00')
        );
});

it('a done plan passes no recipe options', function (): void {
    $plan = MealPlan::factory()->done()->for($this->user)->create();
    Recipe::factory()->breakfast()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('meal-plans.show', $plan))
        ->assertInertia(
            fn ($page) => $page->component('MealPlans/Show', false)
                ->where('recipeOptions', null)
        );
});
