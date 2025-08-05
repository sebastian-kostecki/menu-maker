<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FamilyMemberTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    public function test_user_can_view_family_members_index(): void
    {
        FamilyMember::factory()->count(3)->create(['user_id' => $this->user->id]);
        FamilyMember::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->get(route('family-members.index'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->component('FamilyMembers/Index')
                ->has('familyMembers.data', 3)
        );
    }

    public function test_user_can_view_create_form(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('family-members.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->component('FamilyMembers/Create')
                ->has('genders')
                ->where('genders', ['male', 'female'])
        );
    }

    public function test_user_can_store_family_member(): void
    {
        $data = [
            'first_name' => 'John',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('family-members.store'), $data);

        $response->assertRedirect(route('family-members.index'));
        $response->assertSessionHas('success', 'Family member created successfully.');

        $this->assertDatabaseHas('family_members', [
            'user_id' => $this->user->id,
            'first_name' => 'John',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
        ]);
    }

    public function test_user_can_view_edit_form(): void
    {
        $familyMember = FamilyMember::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get(route('family-members.edit', $familyMember));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->component('FamilyMembers/Edit')
                ->has('familyMember')
                ->has('genders')
                ->where('familyMember.id', $familyMember->id)
        );
    }

    public function test_user_can_update_family_member(): void
    {
        $familyMember = FamilyMember::factory()->create(['user_id' => $this->user->id]);

        $data = [
            'first_name' => 'Jane',
            'birth_date' => '1995-05-05',
            'gender' => 'female',
        ];

        $response = $this->actingAs($this->user)
            ->put(route('family-members.update', $familyMember), $data);

        $response->assertRedirect(route('family-members.index'));
        $response->assertSessionHas('success', 'Family member updated successfully.');

        $this->assertDatabaseHas('family_members', [
            'id' => $familyMember->id,
            'first_name' => 'Jane',
            'birth_date' => '1995-05-05',
            'gender' => 'female',
        ]);
    }

    public function test_user_can_delete_family_member(): void
    {
        $familyMember = FamilyMember::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('family-members.destroy', $familyMember));

        $response->assertRedirect(route('family-members.index'));
        $response->assertSessionHas('success', 'Family member deleted successfully.');

        $this->assertDatabaseMissing('family_members', [
            'id' => $familyMember->id,
        ]);
    }

    public function test_validation_rules_are_applied(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('family-members.store'), []);

        $response->assertSessionHasErrors(['first_name', 'birth_date', 'gender']);
    }

    public function test_birth_date_must_be_before_today(): void
    {
        $data = [
            'first_name' => 'John',
            'birth_date' => now()->addDay()->format('Y-m-d'),
            'gender' => 'male',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('family-members.store'), $data);

        $response->assertSessionHasErrors(['birth_date']);
    }

    public function test_invalid_gender_is_rejected(): void
    {
        $data = [
            'first_name' => 'John',
            'birth_date' => '1990-01-01',
            'gender' => 'invalid',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('family-members.store'), $data);

        $response->assertSessionHasErrors(['gender']);
    }

    public function test_user_cannot_edit_other_users_family_member(): void
    {
        $familyMember = FamilyMember::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->get(route('family-members.edit', $familyMember));

        $response->assertStatus(403);
    }

    public function test_user_cannot_update_other_users_family_member(): void
    {
        $familyMember = FamilyMember::factory()->create(['user_id' => $this->otherUser->id]);

        $data = [
            'first_name' => 'Hacker',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
        ];

        $response = $this->actingAs($this->user)
            ->put(route('family-members.update', $familyMember), $data);

        $response->assertStatus(403);
    }

    public function test_user_cannot_delete_other_users_family_member(): void
    {
        $familyMember = FamilyMember::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('family-members.destroy', $familyMember));

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_family_members(): void
    {
        $response = $this->get(route('family-members.index'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('family-members.create'));
        $response->assertRedirect(route('login'));

        $familyMember = FamilyMember::factory()->create();

        $response = $this->get(route('family-members.edit', $familyMember));
        $response->assertRedirect(route('login'));
    }
}
