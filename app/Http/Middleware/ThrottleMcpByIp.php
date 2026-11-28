<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits `/mcp` per IP before authentication, so refused tokens are limited too. A `throttle:` middleware
 * cannot do this: Laravel's middleware priority always moves it behind `auth`.
 */
class ThrottleMcpByIp
{
    public const MAX_PER_MINUTE = 300;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'mcp-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_MINUTE)) {
            return response()->json(['message' => 'Too Many Attempts.'], 429, [
                'Retry-After' => (string) RateLimiter::availableIn($key),
            ]);
        }

        RateLimiter::hit($key);

        return $next($request);
    }
}
