<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('renders reset password link screen', function (): void {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

it('can request reset password link', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders reset password screen', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

it('resets password with valid token', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

it('resets password with a token issued 59 minutes ago', function (): void {
    Notification::fake();
    $this->freezeTime();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->travel(59)->minutes();

        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();

        return true;
    });
});

it('rejects a token issued 61 minutes ago', function (): void {
    Notification::fake();
    $this->freezeTime();

    $user = User::factory()->create();
    $originalPassword = $user->password;

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $originalPassword) {
        $this->travel(61)->minutes();

        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        expect($user->fresh()->password)->toBe($originalPassword);

        return true;
    });
});
