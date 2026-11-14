<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

use RuntimeException;

/**
 * One refinement attempt failed (HTTP, timeout, unusable response). The message must never carry family data.
 */
class RefinementFailedException extends RuntimeException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
