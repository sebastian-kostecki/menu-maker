<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class InsufficientRecipesException extends RuntimeException
{
    /**
     * @param  array<string, int>  $missing  Missing recipe count per short category.
     */
    public function __construct(public readonly array $missing, string $message)
    {
        parent::__construct($message);
    }
}
