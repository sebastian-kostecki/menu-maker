<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Exception\OAuthServerException;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $params
 * @return array<string, mixed>
 */
function mcpRequest(string $method, array $params = []): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params];
}

/**
 * Captures the context of the one line written to the `mcp` channel.
 *
 * @return ArrayObject<string, mixed>
 */
function captureMcpLogLine(): ArrayObject
{
    $logged = new ArrayObject;
    $channel = Mockery::mock();
    $channel->shouldReceive('info')->once()->andReturnUsing(function (string $message, array $context) use ($logged): void {
        $logged->exchangeArray($context);
    });
    Log::shouldReceive('channel')->with('mcp')->once()->andReturn($channel);

    return $logged;
}

/**
 * @return array<string, mixed>
 */
function secretRecipeArguments(): array
{
    return [
        'name' => 'Tajna zupa babci',
        'category' => 'dinner',
        'calories' => 800,
        'servings' => 4,
        'instructions' => 'Gotuj po cichu.',
        'ingredients' => [['name' => 'Lubczyk', 'quantity' => 20, 'unit' => 'g']],
    ];
}

test('a request without a token is 401 and points at the resource metadata of /mcp', function () {
    $response = $this->postJson('/mcp', mcpRequest('tools/list'));

    $response->assertUnauthorized();
    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');
});

test('a request without a token and without a JSON Accept header is 401, never a redirect to the login page', function () {
    $response = $this->call('POST', '/mcp', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(mcpRequest('tools/list')));

    $response->assertUnauthorized();
});

test('an invalid bearer token is a 401 and is not reported as an error', function () {
    Exceptions::fake();

    $this->withToken('not-a-real-token')->postJson('/mcp', mcpRequest('tools/list'))->assertUnauthorized();

    Exceptions::assertNotReported(OAuthServerException::class);
});

test('a token with the mcp scope lists add-recipe-tool', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    $response = $this->postJson('/mcp', mcpRequest('tools/list'));

    $response->assertOk();
    expect(collect($response->json('result.tools'))->pluck('name')->all())->toBe(['add-recipe-tool']);
});

test('a token without the mcp scope is 403 and saves nothing', function () {
    Passport::actingAs(User::factory()->create(), []);

    $response = $this->postJson('/mcp', mcpRequest('tools/call', [
        'name' => 'add-recipe-tool',
        'arguments' => secretRecipeArguments(),
    ]));

    $response->assertForbidden();
    expect(Recipe::query()->count())->toBe(0);
});

test('the sixty-first call in a minute is 429', function () {
    $this->freezeTime();
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    for ($call = 1; $call <= 60; $call++) {
        $this->postJson('/mcp', mcpRequest('tools/list'))->assertOk();
    }

    $this->postJson('/mcp', mcpRequest('tools/list'))->assertTooManyRequests();
});

test('a saving call writes the recipe for the token user and one log line without its content', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $logged = captureMcpLogLine();

    $response = $this->postJson('/mcp', mcpRequest('tools/call', [
        'name' => 'add-recipe-tool',
        'arguments' => secretRecipeArguments(),
    ]));

    $response->assertOk();
    expect($user->recipes()->pluck('name')->all())->toBe(['Tajna zupa babci'])
        ->and(array_keys($logged->getArrayCopy()))
        ->toBe(['user_id', 'method', 'tool', 'status', 'duration_ms', 'tool_error', 'error_code'])
        ->and($logged->getArrayCopy())->toMatchArray([
            'user_id' => $user->id,
            'method' => 'tools/call',
            'tool' => 'add-recipe-tool',
            'status' => 200,
            'tool_error' => false,
            'error_code' => null,
        ])
        ->and(json_encode($logged->getArrayCopy()))
        ->not->toContain('Tajna zupa')
        ->not->toContain('Lubczyk')
        ->not->toContain('Gotuj');
});

test('a refused recipe is logged as a tool error without the arguments', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
    $logged = captureMcpLogLine();

    $this->postJson('/mcp', mcpRequest('tools/call', [
        'name' => 'add-recipe-tool',
        'arguments' => ['category' => 'lunch'] + secretRecipeArguments(),
    ]))->assertOk();

    expect($logged->getArrayCopy())->toMatchArray(['tool_error' => true, 'error_code' => null])
        ->and(json_encode($logged->getArrayCopy()))->not->toContain('Tajna zupa')->not->toContain('lunch');
});

test('a call without a token is logged with status 401 and no user', function () {
    $logged = captureMcpLogLine();

    $this->postJson('/mcp', mcpRequest('tools/list'))->assertUnauthorized();

    expect($logged->getArrayCopy())->toMatchArray([
        'user_id' => null,
        'method' => 'tools/list',
        'tool' => null,
        'status' => 401,
        'tool_error' => null,
    ]);
});
