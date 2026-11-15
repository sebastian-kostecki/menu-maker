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

    $this->assertDatabaseHas('family_members', [
        'user_id' => $this->user->id,
        'first_name' => 'John',
        'birth_date' => '1990-01-01 00:00:00',
        'gender' => 'male',
    ]);
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
        ->from(route('family-members.index', ['page' => 2]))
        ->put(route('family-members.update', $familyMember), $data);

    $response->assertRedirect(route('family-members.index', ['page' => 2]));

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
        ->from(route('family-members.index', ['page' => 2]))
        ->delete(route('family-members.destroy', $familyMember));

    $response->assertRedirect(route('family-members.index', ['page' => 2]));

    $this->assertDatabaseMissing('family_members', [
        'id' => $familyMember->id,
    ]);
});

it('update and delete never redirect to a foreign referer', function (): void {
    $familyMember = FamilyMember::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->from('https://evil.example/family-members?page=3')
        ->put(route('family-members.update', $familyMember), [
            'first_name' => 'Jane',
            'birth_date' => '1995-05-05',
            'gender' => 'female',
            'weight_kg' => '60',
            'height_cm' => '165',
        ])
        ->assertRedirect(route('family-members.index', ['page' => 3]));

    $this->actingAs($this->user)
        ->from('https://evil.example/')
        ->delete(route('family-members.destroy', $familyMember))
        ->assertRedirect(route('family-members.index'));
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

    $this->post(route('family-members.store'), [])
        ->assertRedirect(route('login'));
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

it('index paginates fifteen members per page', function (): void {
    FamilyMember::factory()->count(16)->create(['user_id' => $this->user->id]);
    FamilyMember::factory()->count(3)->create(['user_id' => $this->otherUser->id]);

    $this->actingAs($this->user)
        ->get(route('family-members.index'))
        ->assertInertia(
            fn ($page) => $page->component('FamilyMembers/Index')
                ->has('familyMembers.data', 15)
                ->where('familyMembers.total', 16)
        );

    $this->actingAs($this->user)
        ->get(route('family-members.index', ['page' => 2]))
        ->assertInertia(
            fn ($page) => $page->component('FamilyMembers/Index')
                ->has('familyMembers.data', 1)
        );
});

it('index redirects a page past the end to the last page', function (): void {
    FamilyMember::factory()->count(16)->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->get(route('family-members.index', ['page' => 5]))
        ->assertRedirect(route('family-members.index', ['page' => 2]));

    FamilyMember::query()->delete();

    $this->actingAs($this->user)
        ->get(route('family-members.index', ['page' => 3]))
        ->assertRedirect(route('family-members.index', ['page' => 1]));
});

it('index serializes birth date as Y-m-d', function (): void {
    FamilyMember::factory()->create([
        'user_id' => $this->user->id,
        'birth_date' => '1990-01-01',
    ]);

    $this->actingAs($this->user)
        ->get(route('family-members.index'))
        ->assertInertia(
            fn ($page) => $page->where('familyMembers.data.0.birth_date', '1990-01-01')
        );
});

it('create and edit pages return not found', function (): void {
    $familyMember = FamilyMember::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->get('/family-members/create')
        ->assertNotFound();

    $this->actingAs($this->user)
        ->get("/family-members/{$familyMember->id}/edit")
        ->assertNotFound();
});

it('validation messages are Polish', function (): void {
    $this->actingAs($this->user)
        ->post(route('family-members.store'), [])
        ->assertSessionHasErrors([
            'first_name' => 'Imię jest wymagane',
            'birth_date' => 'Data urodzenia jest wymagana',
            'gender' => 'Płeć jest wymagana',
        ]);

    $this->actingAs($this->user)
        ->post(route('family-members.store'), [
            'first_name' => 'John',
            'birth_date' => now()->addDay()->format('Y-m-d'),
            'gender' => 'male',
        ])
        ->assertSessionHasErrors(['birth_date' => 'Data urodzenia musi być wcześniejsza niż dzisiaj']);
});
