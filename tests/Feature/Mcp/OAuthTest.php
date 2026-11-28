<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\ClientRepository;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function authorizationQuery(string $clientId): array
{
    return [
        'client_id' => $clientId,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'state' => 'abc-state',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('verifier-', 6), true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ];
}

function claudeClientId(): string
{
    return (string) app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Claude',
        redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
        confidential: false,
        enableDeviceFlow: false,
    )->getKey();
}

test('the protected resource discovery names the resource and the mcp scope', function (string $path, string $resource) {
    $this->getJson('https://menu.test/.well-known/oauth-protected-resource'.$path)
        ->assertOk()
        ->assertJsonPath('resource', $resource)
        ->assertJsonPath('authorization_servers', ['https://menu.test'])
        ->assertJsonPath('scopes_supported', ['mcp:use']);
})->with([
    'root' => ['', 'https://menu.test'],
    '/mcp' => ['/mcp', 'https://menu.test/mcp'],
]);

test('the authorization server discovery lists the endpoints, the scope and PKCE', function () {
    $this->getJson('https://menu.test/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', 'https://menu.test/oauth/authorize')
        ->assertJsonPath('token_endpoint', 'https://menu.test/oauth/token')
        ->assertJsonPath('registration_endpoint', 'https://menu.test/oauth/register')
        ->assertJsonPath('scopes_supported', ['mcp:use'])
        ->assertJsonPath('code_challenge_methods_supported', ['S256']);
});

test('dynamic client registration accepts Claude and loopback redirects', function (string $redirectUri) {
    $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirectUri]])
        ->assertCreated()
        ->assertJsonPath('redirect_uris', [$redirectUri]);
})->with([
    'https://claude.ai/api/mcp/auth_callback',
    'https://claude.com/api/mcp/auth_callback',
    'http://localhost:54321/callback',
    'http://127.0.0.1:6274/oauth/callback',
]);

test('dynamic client registration refuses another domain and creates no client', function () {
    $this->postJson('/oauth/register', ['client_name' => 'Intruz', 'redirect_uris' => ['https://evil.example/callback']])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');

    expect(DB::table('oauth_clients')->count())->toBe(0);
});

test('the eleventh discovery request in a minute is 429', function () {
    $this->freezeTime();

    for ($call = 1; $call <= 10; $call++) {
        $this->getJson('/.well-known/oauth-protected-resource')->assertOk();
    }

    $this->getJson('/.well-known/oauth-protected-resource')->assertTooManyRequests();
});

test('a guest asking for authorization is sent to the login page', function () {
    $this->get('/oauth/authorize?'.http_build_query(authorizationQuery(claudeClientId())))
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toContain('/oauth/authorize?');
});

test('the consent page gets its props, with the mcp scope even when none is requested', function () {
    $clientId = claudeClientId();

    $this->actingAs(User::factory()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($clientId)))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/OAuthAuthorize')
            ->where('client_name', 'Claude')
            ->where('client_id', $clientId)
            ->where('state', 'abc-state')
            ->where('redirect_host', 'claude.ai')
            ->where('scopes', ['Dodawanie przepisów do Twojej bazy'])
            ->where('auth_token', session('authToken'))
            ->where('csrf_token', csrf_token()));
});

test('denying on the consent page returns access_denied to the client and issues nothing', function () {
    $clientId = claudeClientId();
    $this->actingAs(User::factory()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($clientId)))
        ->assertOk();

    $response = $this->delete('/oauth/authorize', ['auth_token' => session('authToken')]);

    expect($response->headers->get('Location'))
        ->toStartWith('https://claude.ai/api/mcp/auth_callback?')
        ->toContain('error=access_denied');
    expect(DB::table('oauth_auth_codes')->count())->toBe(0)
        ->and(DB::table('oauth_access_tokens')->count())->toBe(0);
});

test('an approved authorization yields a short-lived token that /mcp accepts', function () {
    $clientId = claudeClientId();
    $this->actingAs(User::factory()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($clientId)))
        ->assertOk();
    $approval = $this->post('/oauth/authorize', ['auth_token' => session('authToken')]);
    parse_str((string) parse_url((string) $approval->headers->get('Location'), PHP_URL_QUERY), $callback);

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'code' => $callback['code'],
        'code_verifier' => str_repeat('verifier-', 6),
    ])->assertOk();

    // The OAuth server stamps expiry with the real clock, so the lifetimes are checked with a minute of slack.
    $refreshExpiresAt = Carbon::parse(DB::table('oauth_refresh_tokens')->value('expires_at'), 'UTC');

    expect($token->json('expires_in'))->toBeGreaterThan(3540)->toBeLessThanOrEqual(3600);
    expect(abs($refreshExpiresAt->diffInSeconds(now()->addDays(30))))->toBeLessThan(60);

    app('auth')->forgetGuards();

    $this->withToken($token->json('access_token'))
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk();
});

test('the device grant is switched off', function (string $method, string $uri) {
    $this->json($method, $uri)->assertNotFound();
})->with([
    ['GET', '/oauth/device'],
    ['POST', '/oauth/device/code'],
]);

test('other throttled requests from the same IP do not use up the discovery limit', function () {
    $this->freezeTime();

    for ($call = 1; $call <= 10; $call++) {
        $this->postJson('/oauth/token');
    }

    $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk();
});

test('stale OAuth data is purged daily in production', function () {
    // withSchedule() hooks into Artisan::starting(), which the test lifecycle resets between tests.
    Artisan::all();

    $purge = collect(app(Schedule::class)->events())
        ->sole(fn (Event $event): bool => str_contains((string) $event->command, 'passport:purge'));

    expect($purge->expression)->toBe('45 4 * * *')
        ->and($purge->environments)->toBe(['production']);
});

test('no external MCP client is configured', function () {
    expect(config('mcp.clients'))->toBe([]);
});
