<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('updates password with correct current password', function (): void {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
});

it('requires correct current password to update password', function (): void {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/profile');
});

it('signs connected OAuth clients out when the password changes', function (): void {
    $user = User::factory()->create();
    $own = oauthGrantFor($user);
    $other = oauthGrantFor(User::factory()->create());

    $this->actingAs($user)->put('/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    expect($own['access']->fresh()->revoked)->toBeTrue()
        ->and($own['refresh']->fresh()->revoked)->toBeTrue()
        ->and($other['access']->fresh()->revoked)->toBeFalse()
        ->and($other['refresh']->fresh()->revoked)->toBeFalse();
});
