<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

/**
 * The driver is misconfigured (missing key or model); retrying cannot help.
 */
class RefinementConfigurationException extends RefinementFailedException
{
    public function isRetryable(): bool
    {
        return false;
    }
}
