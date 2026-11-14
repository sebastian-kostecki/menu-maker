<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

use RuntimeException;
use Throwable;

/**
 * One refinement attempt failed (HTTP, timeout, unusable response). The message must never carry family data.
 */
class RefinementFailedException extends RuntimeException
{
    /**
     * A 4xx the provider will answer the same way again (bad key, no credit, no access, bad request) is a
     * configuration error; a timeout (408), a rate limit (429) and any 5xx are worth another attempt.
     */
    public static function fromHttpStatus(string $provider, ?int $status, ?Throwable $previous = null): self
    {
        $message = $provider.' responded with HTTP '.($status ?? 'unknown').'.';

        if ($status !== null && $status >= 400 && $status < 500 && ! in_array($status, [408, 429], true)) {
            return new RefinementConfigurationException($message, 0, $previous);
        }

        return new self($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
