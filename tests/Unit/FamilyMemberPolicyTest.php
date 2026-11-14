<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\FamilyMember;
use App\Models\User;
use App\Policies\FamilyMemberPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyMemberPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FamilyMemberPolicy $policy;

    private User $user;

    private User $otherUser;

    private FamilyMember $familyMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new FamilyMemberPolicy;
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->familyMember = FamilyMember::factory()->for($this->user)->create();
    }

    public function test_any_user_can_view_any_and_create(): void
    {
        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_owner_can_view_update_and_delete(): void
    {
        $this->assertTrue($this->policy->view($this->user, $this->familyMember));
        $this->assertTrue($this->policy->update($this->user, $this->familyMember));
        $this->assertTrue($this->policy->delete($this->user, $this->familyMember));
    }

    public function test_other_user_cannot_view_update_or_delete(): void
    {
        $this->assertFalse($this->policy->view($this->otherUser, $this->familyMember));
        $this->assertFalse($this->policy->update($this->otherUser, $this->familyMember));
        $this->assertFalse($this->policy->delete($this->otherUser, $this->familyMember));
    }
}
