<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

/**
 * Proposes practical kitchen amounts for the computed ingredient quantities of one plan, in a single call.
 */
interface IngredientRefiner
{
    /**
     * @param  list<RefinementItem>  $items
     * @return array<string, string> Item key to proposed quantity; every item key appears exactly once.
     *
     * @throws RefinementFailedException On HTTP, timeout, non-JSON, a missing, unknown or duplicate key or a non-numeric value.
     */
    public function refine(array $items): array;
}
