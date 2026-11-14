<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

it('renders login screen', function (): void {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

it('authenticates users using login screen', function (): void {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

it('does not authenticate with invalid password', function (): void {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

it('logs out users', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

/**
 * Logs in through the login endpoint on the database session driver and returns the cookies the browser would keep.
 *
 * @return array<string, string>
 */
function loginAndCaptureCookies(User $user, bool $remember = false): array
{
    config(['session.driver' => 'database']);
    app('session')->forgetDrivers();

    $response = test()->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => $remember,
    ]);

    $cookies = [];

    foreach ($response->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }

    return $cookies;
}

/**
 * Requests a page as a fresh client holding only the given cookies, so no cached guard or session state survives.
 * The cookie values come from a response, so they are already encrypted and are sent as they are.
 *
 * @param  array<string, string>  $cookies
 */
function getWithOnlyCookies(string $uri, array $cookies): TestResponse
{
    Auth::forgetGuards();
    app('session')->forgetDrivers();

    if (app()->bound('session.store')) {
        app()->forgetInstance('session.store');
    }

    return test()->flushSession()->withUnencryptedCookies($cookies)->get($uri);
}

it('keeps the session alive 23 hours 59 minutes after the last request', function (): void {
    $this->freezeTime();
    $cookies = loginAndCaptureCookies(User::factory()->create());

    $this->travel(23 * 60 + 59)->minutes();
    getWithOnlyCookies('/dashboard', $cookies)->assertOk();
});

it('rejects the session 24 hours 1 minute after the last request', function (): void {
    $this->freezeTime();
    $cookies = loginAndCaptureCookies(User::factory()->create());

    $this->travel(24 * 60 + 1)->minutes();

    getWithOnlyCookies('/dashboard', $cookies)->assertRedirect(route('login'));
});

it('resets the idle clock on every request', function (): void {
    $this->freezeTime();
    $cookies = loginAndCaptureCookies(User::factory()->create());

    $this->travel(20)->hours();
    getWithOnlyCookies('/dashboard', $cookies)->assertOk();

    $this->travel(20)->hours();
    getWithOnlyCookies('/dashboard', $cookies)->assertOk();
});

it('re-authenticates a remembered user after the session expired', function (): void {
    $this->freezeTime();
    $user = User::factory()->create();
    $cookies = loginAndCaptureCookies($user, remember: true);

    expect($cookies)->toHaveKey(Auth::guard()->getRecallerName());

    $this->travel(25)->hours();

    getWithOnlyCookies('/dashboard', $cookies)->assertOk();
    $this->assertAuthenticatedAs($user);
    expect(Auth::viaRemember())->toBeTrue();
});
