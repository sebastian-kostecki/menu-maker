<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('redirects a guest from the root to the login page', function (): void {
    $this->get('/')->assertRedirect('/dashboard');

    $this->followingRedirects()
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('redirects a signed-in user from the root to the dashboard', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect('/dashboard');

    $this->actingAs($user)
        ->followingRedirects()
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
});
