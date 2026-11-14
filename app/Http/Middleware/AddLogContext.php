<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class AddLogContext
{
    /**
     * Add the authenticated user's id to the log context.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId !== null) {
            Context::add('user_id', (int) $userId);
        }

        return $next($request);
    }
}
