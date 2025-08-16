<?php

declare(strict_types=1);

use App\Jobs\GenerateMealPlanJob;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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
        fn($page) => $page->component('MealPlans/Index', false)
            ->has('mealPlans.data', 3)
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
        fn($page) => $page->has('mealPlans.data', 2)
    );
});

it('user can view own meal plan', function (): void {
    $mealPlan = MealPlan::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user)->get(route('meal-plans.show', $mealPlan));

    $response->assertStatus(200);
    $response->assertInertia(
        fn($page) => $page->component('MealPlans/Show', false)
            ->has('mealPlan')
            ->where('mealPlan.id', $mealPlan->id)
    );
});

it("user cannot view other user's meal plan", function (): void {
    $mealPlan = MealPlan::factory()->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->get(route('meal-plans.show', $mealPlan));

    $response->assertStatus(403);
});

it('user can generate meal plan', function (): void {
    Queue::fake();

    $mealPlanData = [
        'start_date' => now()->addDays(7)->format('Y-m-d'),
    ];

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), $mealPlanData);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data' => [
            'id',
            'start_date',
            'end_date',
            'status',
            'links' => ['self'],
        ],
    ]);

    $this->assertDatabaseHas('meal_plans', [
        'user_id' => $this->user->id,
        'start_date' => $mealPlanData['start_date'],
        'status' => 'pending',
    ]);

    Queue::assertPushed(GenerateMealPlanJob::class, function ($job) {
        return $job->mealPlan->user_id === $this->user->id && ! $job->regenerate;
    });
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

it('respects rate limiting on generation', function (): void {
    $startDate = now()->addDays(7)->format('Y-m-d');

    for ($i = 1; $i <= 5; $i++) {
        $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
            'start_date' => now()->addDays($i)->format('Y-m-d'),
        ]);
    }

    $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
        'start_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['rate_limit']);
});

it('user can regenerate completed meal plan', function (): void {
    Queue::fake();

    $mealPlan = MealPlan::factory()->done()->for($this->user)->create();

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), [
        'regenerate' => true,
    ]);

    $response->assertStatus(202);
    $response->assertJsonStructure([
        'message',
        'data' => ['id', 'status'],
    ]);

    $this->assertDatabaseHas('meal_plans', [
        'id' => $mealPlan->id,
        'status' => 'pending',
    ]);

    Queue::assertPushed(GenerateMealPlanJob::class, function ($job) use ($mealPlan) {
        return $job->mealPlan->id === $mealPlan->id && $job->regenerate;
    });
});

it('user can regenerate failed meal plan', function (): void {
    Queue::fake();

    $mealPlan = MealPlan::factory()->error()->for($this->user)->create();

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), [
        'regenerate' => true,
    ]);

    $response->assertStatus(202);
    Queue::assertPushed(GenerateMealPlanJob::class);
});

it('user cannot regenerate processing meal plan', function (): void {
    $mealPlan = MealPlan::factory()->processing()->for($this->user)->create();

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), [
        'regenerate' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
});

it('user cannot regenerate pending meal plan', function (): void {
    $mealPlan = MealPlan::factory()->pending()->for($this->user)->create();

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), [
        'regenerate' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
});

it('requires regenerate flag', function (): void {
    $mealPlan = MealPlan::factory()->done()->for($this->user)->create();

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['regenerate']);
});

it("user cannot regenerate other user's meal plan", function (): void {
    $mealPlan = MealPlan::factory()->done()->for($this->otherUser)->create();

    $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), [
        'regenerate' => true,
    ]);

    $response->assertStatus(403);
});

it('user can delete own meal plan', function (): void {
    Storage::fake('local');

    $mealPlan = MealPlan::factory()
        ->withPdf()
        ->for($this->user)
        ->create();

    Storage::put($mealPlan->pdf_path, 'fake pdf content');

    $response = $this->actingAs($this->user)->deleteJson(route('meal-plans.destroy', $mealPlan));

    $response->assertStatus(204);

    $this->assertDatabaseMissing('meal_plans', ['id' => $mealPlan->id]);
    Storage::assertMissing($mealPlan->pdf_path);
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
