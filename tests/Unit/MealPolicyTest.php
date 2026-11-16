<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Policies\MealPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected MealPolicy $policy;

    protected User $user;

    protected User $otherUser;

    protected Meal $meal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new MealPolicy;
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $plan = MealPlan::factory()->draft()->for($this->user)->create();
        $this->meal = Meal::query()->create([
            'meal_plan_id' => $plan->id,
            'recipe_id' => Recipe::factory()->breakfast()->for($this->user)->create()->id,
            'meal_date' => $plan->start_date,
            'meal_category' => 'breakfast',
        ]);
    }

    public function test_update_allows_owner_of_the_plan(): void
    {
        $this->assertTrue($this->policy->update($this->user, $this->meal));
    }

    public function test_update_denies_non_owner(): void
    {
        $this->assertFalse($this->policy->update($this->otherUser, $this->meal));
    }
}
