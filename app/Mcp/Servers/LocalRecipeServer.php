<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * The stdio variant: over stdio there is no middleware, so the only user is the one named in
 * `MCP_LOCAL_USER`, and only in the `local` environment. `RecipeServer` has no such code,
 * so a web endpoint serving it can never reach it.
 */
class LocalRecipeServer extends RecipeServer
{
    protected function boot(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('The local MCP server runs only in the local environment.');
        }

        $identifier = config('services.mcp.local_user');

        if (! is_string($identifier) || trim($identifier) === '') {
            throw new RuntimeException('Set MCP_LOCAL_USER (an e-mail or an id) to start the local MCP server.');
        }

        $identifier = trim($identifier);

        $user = ctype_digit($identifier)
            ? User::query()->find((int) $identifier)
            : User::query()->where('email', $identifier)->first();

        if ($user === null) {
            throw new RuntimeException("MCP_LOCAL_USER [{$identifier}] matches no user.");
        }

        // setUser, not login: stdio has no session to write.
        Auth::guard('web')->setUser($user);
    }
}
