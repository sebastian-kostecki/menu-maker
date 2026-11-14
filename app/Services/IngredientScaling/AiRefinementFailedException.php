<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

use RuntimeException;
use Throwable;

/**
 * Reported once when AI refinement gave up; the plan keeps its computed amounts.
 */
class AiRefinementFailedException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('AI ingredient refinement failed; computed quantities were kept.', 0, $previous);
    }
}
