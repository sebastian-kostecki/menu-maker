<?php

declare(strict_types=1);

use App\Mcp\Servers\LocalRecipeServer;
use App\Mcp\Servers\RecipeServer;
use App\Mcp\Tools\AddRecipeTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Facades\Mcp;

uses(RefreshDatabase::class);

test('the server registers only add-recipe-tool', function () {
    RecipeServer::actingAs(User::factory()->create())->tools()->assertRegistered(AddRecipeTool::class);

    $registered = (new ReflectionClass(RecipeServer::class))->getDefaultProperties()['tools'];

    expect($registered)->toBe([AddRecipeTool::class]);
});

test('the local server is registered as the stdio handle menu-maker', function () {
    expect(Mcp::getLocalServer('menu-maker'))->not->toBeNull();
});

test('the local server signs in the user named by e-mail or by id', function (string $attribute) {
    $this->app['env'] = 'local';
    $user = User::factory()->create(['email' => 'local@example.test']);
    config()->set('services.mcp.local_user', (string) $user->{$attribute});

    LocalRecipeServer::tools()->assertRegistered(AddRecipeTool::class);

    expect(Auth::guard('web')->user()?->is($user))->toBeTrue();
})->with(['email', 'id']);

test('the local server refuses to boot outside the local environment', function () {
    User::factory()->create(['email' => 'local@example.test']);
    config()->set('services.mcp.local_user', 'local@example.test');

    expect(fn () => LocalRecipeServer::tools())->toThrow(RuntimeException::class, 'local environment');
    expect(Auth::guard('web')->user())->toBeNull();
});

test('the local server refuses to boot without a configured, existing user', function (?string $configured) {
    $this->app['env'] = 'local';
    User::factory()->create(['email' => 'local@example.test']);
    config()->set('services.mcp.local_user', $configured);

    expect(fn () => LocalRecipeServer::tools())->toThrow(RuntimeException::class, 'MCP_LOCAL_USER');
    expect(Auth::guard('web')->user())->toBeNull();
})->with([
    'not set' => [null],
    'blank' => ['  '],
    'unknown e-mail' => ['nobody@example.test'],
    'unknown id' => ['999999'],
]);
