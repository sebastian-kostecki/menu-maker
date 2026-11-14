<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;

uses(RefreshDatabase::class);

it('adds user_id to the log context for authenticated requests', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')->assertOk();

    expect(Context::has('user_id'))->toBeTrue()
        ->and(Context::get('user_id'))->toBe($user->id);
});

it('does not add user_id to the log context for guests', function (): void {
    $this->get('/')->assertOk();

    expect(Context::has('user_id'))->toBeFalse();
});
