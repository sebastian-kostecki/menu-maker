<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders registration screen', function (): void {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

it('registers new users', function (): void {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

it('returns the Polish unique message for an existing e-mail', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['email' => 'Wartość pola adres e-mail jest już zajęta.']);
});

it('returns the Polish confirmed message for mismatched passwords', function (): void {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors(['password' => 'Potwierdzenie pola hasło nie jest zgodne.']);
});

it('returns the Polish min message for a too short password', function (): void {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'abc',
        'password_confirmation' => 'abc',
    ])->assertSessionHasErrors(['password' => 'Pole hasło musi mieć co najmniej 8 znaków.']);
});
