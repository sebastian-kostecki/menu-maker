<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Recipe;
use App\Models\User;
use App\Policies\RecipePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected RecipePolicy $policy;

    protected User $user;

    protected User $otherUser;

    protected Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new RecipePolicy;
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->recipe = Recipe::factory()->for($this->user)->create();
    }

    public function test_user_can_view_any_recipes(): void
    {
        $this->assertTrue($this->policy->viewAny($this->user));
    }

    public function test_user_can_view_own_recipe(): void
    {
        $this->assertTrue($this->policy->view($this->user, $this->recipe));
    }

    public function test_user_cannot_view_other_users_recipe(): void
    {
        $this->assertFalse($this->policy->view($this->otherUser, $this->recipe));
    }

    public function test_user_can_create_recipes(): void
    {
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_can_update_own_recipe(): void
    {
        $this->assertTrue($this->policy->update($this->user, $this->recipe));
    }

    public function test_user_cannot_update_other_users_recipe(): void
    {
        $this->assertFalse($this->policy->update($this->otherUser, $this->recipe));
    }

    public function test_user_can_delete_own_recipe(): void
    {
        $this->assertTrue($this->policy->delete($this->user, $this->recipe));
    }

    public function test_user_cannot_delete_other_users_recipe(): void
    {
        $this->assertFalse($this->policy->delete($this->otherUser, $this->recipe));
    }

    public function test_user_can_restore_own_recipe(): void
    {
        $this->assertTrue($this->policy->restore($this->user, $this->recipe));
    }

    public function test_user_cannot_restore_other_users_recipe(): void
    {
        $this->assertFalse($this->policy->restore($this->otherUser, $this->recipe));
    }

    public function test_user_can_force_delete_own_recipe(): void
    {
        $this->assertTrue($this->policy->forceDelete($this->user, $this->recipe));
    }

    public function test_user_cannot_force_delete_other_users_recipe(): void
    {
        $this->assertFalse($this->policy->forceDelete($this->otherUser, $this->recipe));
    }
}
