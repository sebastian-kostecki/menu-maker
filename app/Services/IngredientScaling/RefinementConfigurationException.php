<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

/**
 * The driver is misconfigured (missing key or model, or the provider rejected the request with a permanent 4xx);
 * retrying cannot help.
 */
class RefinementConfigurationException extends RefinementFailedException
{
    public function isRetryable(): bool
    {
        return false;
    }
}
