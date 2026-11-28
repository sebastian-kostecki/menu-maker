<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logs one line per remote MCP call to the `mcp` channel: user id (null when the call is refused before
 * authentication), JSON-RPC method, tool name, HTTP status, duration, and from the JSON response only
 * `tool_error` (`result.isError` of a tools/call) and `error_code` (the JSON-RPC `error.code`).
 * Never the arguments, the recipe text or any response content.
 */
class LogMcpCall
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        $response = $next($request);

        $method = $request->input('method');
        $toolName = $method === 'tools/call' ? $request->input('params.name') : null;
        $body = $this->jsonBodyOf($response);
        $toolError = $method === 'tools/call' && is_array($body['result'] ?? null)
            ? ($body['result']['isError'] ?? false) === true
            : null;
        $errorCode = is_array($body['error'] ?? null) && is_int($body['error']['code'] ?? null)
            ? $body['error']['code']
            : null;

        Log::channel('mcp')->info('mcp call', [
            'user_id' => $request->user()?->getAuthIdentifier(),
            'method' => $this->loggable($method),
            'tool' => $this->loggable($toolName),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'tool_error' => $toolError,
            'error_code' => $errorCode,
        ]);

        return $response;
    }

    /**
     * A client-chosen value made safe for one log line: protocol name characters only, at most 100.
     */
    private function loggable(mixed $value): ?string
    {
        return is_string($value)
            ? substr((string) preg_replace('/[^A-Za-z0-9_.\/-]/', '', $value), 0, 100)
            : null;
    }

    /**
     * The decoded JSON-RPC body, or null for a streamed or non-JSON response.
     *
     * @return array<mixed>|null
     */
    private function jsonBodyOf(Response $response): ?array
    {
        if ($response instanceof StreamedResponse) {
            return null;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }
}
