<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class);
uses(WithFaker::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

it('user can view family members index', function (): void {
    FamilyMember::factory()->count(3)->create(['user_id' => $this->user->id]);
    FamilyMember::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

    $response = $this->actingAs($this->user)
        ->get(route('family-members.index'));

    $response->assertStatus(200);
    $response->assertInertia(
        fn($page) => $page->component('FamilyMembers/Index')
            ->has('familyMembers.data', 3)
    );
});

it('user can view create form', function (): void {
    $response = $this->actingAs($this->user)
        ->get(route('family-members.create'));

    $response->assertStatus(200);
    $response->assertInertia(
        fn($page) => $page->component('FamilyMembers/Create', false)
            ->has('genders')
            ->where('genders', ['male', 'female'])
    );
});

it('user can store family member', function (): void {
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
});

it('user can view edit form', function (): void {
    $familyMember = FamilyMember::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->get(route('family-members.edit', $familyMember));

    $response->assertStatus(200);
    $response->assertInertia(
        fn($page) => $page->component('FamilyMembers/Edit', false)
            ->has('familyMember')
            ->has('genders')
            ->where('familyMember.id', $familyMember->id)
    );
});

it('user can update family member', function (): void {
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
});

it('user can delete family member', function (): void {
    $familyMember = FamilyMember::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->delete(route('family-members.destroy', $familyMember));

    $response->assertRedirect(route('family-members.index'));
    $response->assertSessionHas('success', 'Family member deleted successfully.');

    $this->assertDatabaseMissing('family_members', [
        'id' => $familyMember->id,
    ]);
});

it('applies validation rules on store', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('family-members.store'), []);

    $response->assertSessionHasErrors(['first_name', 'birth_date', 'gender']);
});

it('requires birth date to be before today', function (): void {
    $data = [
        'first_name' => 'John',
        'birth_date' => now()->addDay()->format('Y-m-d'),
        'gender' => 'male',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('family-members.store'), $data);

    $response->assertSessionHasErrors(['birth_date']);
});

it('rejects invalid gender', function (): void {
    $data = [
        'first_name' => 'John',
        'birth_date' => '1990-01-01',
        'gender' => 'invalid',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('family-members.store'), $data);

    $response->assertSessionHasErrors(['gender']);
});

it("user cannot edit other user's family member", function (): void {
    $familyMember = FamilyMember::factory()->create(['user_id' => $this->otherUser->id]);

    $response = $this->actingAs($this->user)
        ->get(route('family-members.edit', $familyMember));

    $response->assertStatus(403);
});

it("user cannot update other user's family member", function (): void {
    $familyMember = FamilyMember::factory()->create(['user_id' => $this->otherUser->id]);

    $data = [
        'first_name' => 'Hacker',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
    ];

    $response = $this->actingAs($this->user)
        ->put(route('family-members.update', $familyMember), $data);

    $response->assertStatus(403);
});

it("user cannot delete other user's family member", function (): void {
    $familyMember = FamilyMember::factory()->create(['user_id' => $this->otherUser->id]);

    $response = $this->actingAs($this->user)
        ->delete(route('family-members.destroy', $familyMember));

    $response->assertStatus(403);
});

it('guest cannot access family members', function (): void {
    $response = $this->get(route('family-members.index'));
    $response->assertRedirect(route('login'));

    $response = $this->get(route('family-members.create'));
    $response->assertRedirect(route('login'));

    $familyMember = FamilyMember::factory()->create();

    $response = $this->get(route('family-members.edit', $familyMember));
    $response->assertRedirect(route('login'));
});
