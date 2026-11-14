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
        fn ($page) => $page->component('FamilyMembers/Index')
            ->has('familyMembers.data', 3)
    );
});

it('user can view create form', function (): void {
    $response = $this->actingAs($this->user)
        ->get(route('family-members.create'));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->component('FamilyMembers/Create', false)
            ->has('genders')
            ->where('genders', ['male', 'female'])
    );
});

it('user can store family member', function (): void {
    $data = [
        'first_name' => 'John',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
        'weight_kg' => '80',
        'height_cm' => '180',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('family-members.store'), $data);

    $response->assertRedirect(route('family-members.index'));
    $response->assertSessionHas('success', 'Family member created successfully.');

    $this->assertDatabaseHas('family_members', [
        'user_id' => $this->user->id,
        'first_name' => 'John',
        'birth_date' => '1990-01-01 00:00:00',
        'gender' => 'male',
    ]);
});

it('user can view edit form', function (): void {
    $familyMember = FamilyMember::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->get(route('family-members.edit', $familyMember));

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->component('FamilyMembers/Edit', false)
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
        'weight_kg' => '60',
        'height_cm' => '165',
    ];

    $response = $this->actingAs($this->user)
        ->put(route('family-members.update', $familyMember), $data);

    $response->assertRedirect(route('family-members.index'));
    $response->assertSessionHas('success', 'Family member updated successfully.');

    $this->assertDatabaseHas('family_members', [
        'id' => $familyMember->id,
        'first_name' => 'Jane',
        'birth_date' => '1995-05-05 00:00:00',
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

it('rejects an adult without weight or height', function (): void {
    $data = [
        'first_name' => 'John',
        'birth_date' => now()->subYears(19)->format('Y-m-d'),
        'gender' => 'male',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('family-members.store'), $data);

    $response->assertSessionHasErrors([
        'weight_kg' => 'Podaj wagę (wymagana od 19 lat).',
        'height_cm' => 'Podaj wzrost (wymagany od 19 lat).',
    ]);
});

it('accepts a child without weight or height', function (): void {
    $data = [
        'first_name' => 'Ola',
        'birth_date' => now()->subYears(18)->addDay()->format('Y-m-d'),
        'gender' => 'female',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('family-members.store'), $data);

    $response->assertSessionDoesntHaveErrors();
    $this->assertDatabaseHas('family_members', ['first_name' => 'Ola', 'weight_kg' => null, 'height_cm' => null]);
});

it('stores weight and height for an adult', function (): void {
    $data = [
        'first_name' => 'John',
        'birth_date' => '1985-03-03',
        'gender' => 'male',
        'weight_kg' => '80.5',
        'height_cm' => '180',
    ];

    $this->actingAs($this->user)
        ->post(route('family-members.store'), $data)
        ->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('family_members', [
        'first_name' => 'John',
        'weight_kg' => '80.5',
        'height_cm' => '180.0',
    ]);
});

it('enforces the weight and height range boundaries', function (): void {
    $base = ['first_name' => 'John', 'birth_date' => '1985-03-03', 'gender' => 'male'];

    $this->actingAs($this->user)
        ->post(route('family-members.store'), $base + ['weight_kg' => 29.9, 'height_cm' => 99.9])
        ->assertSessionHasErrors([
            'weight_kg' => 'Waga musi wynosić od 30 do 300 kg.',
            'height_cm' => 'Wzrost musi wynosić od 100 do 250 cm.',
        ]);

    $this->actingAs($this->user)
        ->post(route('family-members.store'), $base + ['weight_kg' => 300.1, 'height_cm' => 250.1])
        ->assertSessionHasErrors(['weight_kg', 'height_cm']);

    $this->actingAs($this->user)
        ->post(route('family-members.store'), $base + ['weight_kg' => 30, 'height_cm' => 100])
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($this->user)
        ->post(route('family-members.store'), $base + ['first_name' => 'Max', 'weight_kg' => 300, 'height_cm' => 250])
        ->assertSessionDoesntHaveErrors();
});
