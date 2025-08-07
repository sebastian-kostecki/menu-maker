<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MealPlan;
use App\Models\User;
use App\Policies\MealPlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected MealPlanPolicy $policy;
    protected User $user;
    protected User $otherUser;
    protected MealPlan $mealPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new MealPlanPolicy;
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->mealPlan = MealPlan::factory()->for($this->user)->create();
    }

    public function test_view_any_allows_authenticated_users(): void
    {
        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->viewAny($this->otherUser));
    }

    public function test_view_allows_owner(): void
    {
        $this->assertTrue($this->policy->view($this->user, $this->mealPlan));
    }

    public function test_view_denies_non_owner(): void
    {
        $this->assertFalse($this->policy->view($this->otherUser, $this->mealPlan));
    }

    public function test_create_allows_authenticated_users(): void
    {
        $this->assertTrue($this->policy->create($this->user));
        $this->assertTrue($this->policy->create($this->otherUser));
    }

    public function test_update_allows_owner_when_not_processing(): void
    {
        $this->mealPlan->update(['status' => 'pending']);
        $this->assertTrue($this->policy->update($this->user, $this->mealPlan));

        $this->mealPlan->update(['status' => 'done']);
        $this->assertTrue($this->policy->update($this->user, $this->mealPlan));

        $this->mealPlan->update(['status' => 'error']);
        $this->assertTrue($this->policy->update($this->user, $this->mealPlan));
    }

    public function test_update_allows_owner_when_processing(): void
    {
        $this->mealPlan->update(['status' => 'processing']);
        // Policy now allows update for owners - status check moved to request validation
        $this->assertTrue($this->policy->update($this->user, $this->mealPlan));
    }

    public function test_update_denies_non_owner(): void
    {
        $this->mealPlan->update(['status' => 'pending']);
        $this->assertFalse($this->policy->update($this->otherUser, $this->mealPlan));
    }

    public function test_delete_allows_owner_when_not_processing(): void
    {
        $this->mealPlan->update(['status' => 'pending']);
        $this->assertTrue($this->policy->delete($this->user, $this->mealPlan));

        $this->mealPlan->update(['status' => 'done']);
        $this->assertTrue($this->policy->delete($this->user, $this->mealPlan));

        $this->mealPlan->update(['status' => 'error']);
        $this->assertTrue($this->policy->delete($this->user, $this->mealPlan));
    }

    public function test_delete_denies_owner_when_processing(): void
    {
        $this->mealPlan->update(['status' => 'processing']);
        $this->assertFalse($this->policy->delete($this->user, $this->mealPlan));
    }

    public function test_delete_denies_non_owner(): void
    {
        $this->mealPlan->update(['status' => 'pending']);
        $this->assertFalse($this->policy->delete($this->otherUser, $this->mealPlan));
    }

    public function test_restore_allows_owner(): void
    {
        $this->assertTrue($this->policy->restore($this->user, $this->mealPlan));
    }

    public function test_restore_denies_non_owner(): void
    {
        $this->assertFalse($this->policy->restore($this->otherUser, $this->mealPlan));
    }

    public function test_force_delete_allows_owner_when_not_processing(): void
    {
        $this->mealPlan->update(['status' => 'pending']);
        $this->assertTrue($this->policy->forceDelete($this->user, $this->mealPlan));

        $this->mealPlan->update(['status' => 'done']);
        $this->assertTrue($this->policy->forceDelete($this->user, $this->mealPlan));

        $this->mealPlan->update(['status' => 'error']);
        $this->assertTrue($this->policy->forceDelete($this->user, $this->mealPlan));
    }

    public function test_force_delete_denies_owner_when_processing(): void
    {
        $this->mealPlan->update(['status' => 'processing']);
        $this->assertFalse($this->policy->forceDelete($this->user, $this->mealPlan));
    }

    public function test_force_delete_denies_non_owner(): void
    {
        $this->mealPlan->update(['status' => 'pending']);
        $this->assertFalse($this->policy->forceDelete($this->otherUser, $this->mealPlan));
    }
}
