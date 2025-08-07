<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateMealPlanJob;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MealPlanControllerTest extends TestCase
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

    public function test_authenticated_user_can_view_meal_plan_index(): void
    {
        MealPlan::factory()->count(3)->for($this->user)->create();
        MealPlan::factory()->count(2)->for($this->otherUser)->create();

        $response = $this->actingAs($this->user)->get(route('meal-plans.index'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page->component('MealPlans/Index', false)
                ->has('mealPlans.data', 3) // Only user's meal plans
        );
    }

    public function test_guest_cannot_access_meal_plans(): void
    {
        $response = $this->get(route('meal-plans.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_can_filter_by_status(): void
    {
        MealPlan::factory()->pending()->for($this->user)->create();
        MealPlan::factory()->done()->count(2)->for($this->user)->create();

        $response = $this->actingAs($this->user)
            ->get(route('meal-plans.index', ['filter' => ['status' => 'done']]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page->has('mealPlans.data', 2)
        );
    }

    public function test_user_can_view_own_meal_plan(): void
    {
        $mealPlan = MealPlan::factory()->for($this->user)->create();

        $response = $this->actingAs($this->user)->get(route('meal-plans.show', $mealPlan));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page->component('MealPlans/Show', false)
                ->has('mealPlan')
                ->where('mealPlan.id', $mealPlan->id)
        );
    }

    public function test_user_cannot_view_other_users_meal_plan(): void
    {
        $mealPlan = MealPlan::factory()->for($this->otherUser)->create();

        $response = $this->actingAs($this->user)->get(route('meal-plans.show', $mealPlan));

        $response->assertStatus(403);
    }

    public function test_user_can_generate_meal_plan(): void
    {
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
            return $job->mealPlan->user_id === $this->user->id && !$job->regenerate;
        });
    }

    public function test_meal_plan_generation_requires_valid_start_date(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
            'start_date' => now()->subDay()->format('Y-m-d'), // Yesterday
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_date']);
    }

    public function test_meal_plan_generation_prevents_duplicate_start_dates(): void
    {
        $startDate = now()->addDays(7)->format('Y-m-d');

        MealPlan::factory()->for($this->user)->create(['start_date' => $startDate]);

        $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
            'start_date' => $startDate,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_date']);
    }

    public function test_meal_plan_generation_respects_rate_limiting(): void
    {
        $startDate = now()->addDays(7)->format('Y-m-d');

        // Make 5 requests (rate limit)
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
                'start_date' => now()->addDays($i)->format('Y-m-d'),
            ]);
        }

        // 6th request should be rate limited
        $response = $this->actingAs($this->user)->postJson(route('meal-plans.store'), [
            'start_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rate_limit']);
    }

    public function test_user_can_regenerate_completed_meal_plan(): void
    {
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
    }

    public function test_user_can_regenerate_failed_meal_plan(): void
    {
        Queue::fake();

        $mealPlan = MealPlan::factory()->error()->for($this->user)->create();

        $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), [
            'regenerate' => true,
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(GenerateMealPlanJob::class);
    }

    public function test_user_cannot_regenerate_processing_meal_plan(): void
    {
        $mealPlan = MealPlan::factory()->processing()->for($this->user)->create();

        $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), [
            'regenerate' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_user_cannot_regenerate_pending_meal_plan(): void
    {
        $mealPlan = MealPlan::factory()->pending()->for($this->user)->create();

        $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), [
            'regenerate' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_regeneration_requires_regenerate_flag(): void
    {
        $mealPlan = MealPlan::factory()->done()->for($this->user)->create();

        $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['regenerate']);
    }

    public function test_user_cannot_regenerate_other_users_meal_plan(): void
    {
        $mealPlan = MealPlan::factory()->done()->for($this->otherUser)->create();

        $response = $this->actingAs($this->user)->putJson(route('meal-plans.update', $mealPlan), [
            'regenerate' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_own_meal_plan(): void
    {
        Storage::fake('local');

        $mealPlan = MealPlan::factory()
            ->withPdf()
            ->for($this->user)
            ->create();

        // Create a fake PDF file
        Storage::put($mealPlan->pdf_path, 'fake pdf content');

        $response = $this->actingAs($this->user)->deleteJson(route('meal-plans.destroy', $mealPlan));

        $response->assertStatus(204);

        $this->assertDatabaseMissing('meal_plans', ['id' => $mealPlan->id]);
        Storage::assertMissing($mealPlan->pdf_path);
    }

    public function test_user_cannot_delete_processing_meal_plan(): void
    {
        $mealPlan = MealPlan::factory()->processing()->for($this->user)->create();

        $response = $this->actingAs($this->user)->deleteJson(route('meal-plans.destroy', $mealPlan));

        $response->assertStatus(403);
        $this->assertDatabaseHas('meal_plans', ['id' => $mealPlan->id]);
    }

    public function test_user_cannot_delete_other_users_meal_plan(): void
    {
        $mealPlan = MealPlan::factory()->for($this->otherUser)->create();

        $response = $this->actingAs($this->user)->deleteJson(route('meal-plans.destroy', $mealPlan));

        $response->assertStatus(403);
        $this->assertDatabaseHas('meal_plans', ['id' => $mealPlan->id]);
    }

    public function test_api_responses_are_json_when_expected(): void
    {
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
    }
}
