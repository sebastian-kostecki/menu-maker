<?php

declare(strict_types=1);

use App\Http\Middleware\LogMcpCall;
use App\Mcp\Servers\LocalRecipeServer;
use App\Mcp\Servers\RecipeServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

/*
|--------------------------------------------------------------------------
| MCP servers
|--------------------------------------------------------------------------
|
| `php artisan mcp:start menu-maker` serves the local stdio server. `/mcp` serves
| `RecipeServer` (never the local one) behind Passport OAuth: discovery and client
| registration are limited to 10 requests a minute per IP, and a token without the
| `mcp:use` scope is refused with 403. `LogMcpCall` comes before the auth and
| throttle middleware, so refused calls (401, 403, 429) are logged too.
|
*/

Mcp::local('menu-maker', LocalRecipeServer::class);

Route::middleware('throttle:10,1')->group(fn () => Mcp::oauthRoutes());

Mcp::web('/mcp', RecipeServer::class)
    ->middleware([LogMcpCall::class, 'auth:api', CheckToken::using('mcp:use'), 'throttle:mcp']);
