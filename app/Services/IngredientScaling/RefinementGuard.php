<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

/**
 * Accepts or rejects one AI proposal against the computed quantity.
 */
class RefinementGuard
{
    private const SCALE = 12;

    private const MAX_DEVIATION = '0.25';

    /**
     * Returns the proposal as a 2-decimal string when it is acceptable, otherwise null (keep the computed value).
     */
    public function accept(RefinementItem $item, string $proposal): ?string
    {
        if (preg_match('/^\d+(\.\d+)?$/', $proposal) !== 1) {
            return null;
        }

        $computed = $item->computedQuantity;

        $valid = $item->unitCode === 'szt'
            ? $this->acceptablePieces($computed, $proposal)
            : $this->withinDeviation($computed, $proposal);

        return $valid ? bcadd($proposal, '0', 2) : null;
    }

    private function acceptablePieces(string $computed, string $proposal): bool
    {
        if (bccomp($proposal, bcadd($proposal, '0', 0), self::SCALE) !== 0) {
            return false;
        }

        if (bccomp($proposal, '0', self::SCALE) === 0) {
            return bccomp($computed, '0.5', self::SCALE) < 0;
        }

        $floor = bcadd($computed, '0', 0);
        $ceil = bccomp($computed, $floor, self::SCALE) === 0 ? $floor : bcadd($floor, '1', 0);

        return bccomp($proposal, $floor, self::SCALE) === 0 || bccomp($proposal, $ceil, self::SCALE) === 0;
    }

    private function withinDeviation(string $computed, string $proposal): bool
    {
        $difference = bcsub($proposal, $computed, self::SCALE);

        if (bccomp($difference, '0', self::SCALE) < 0) {
            $difference = bcmul($difference, '-1', self::SCALE);
        }

        return bccomp($difference, bcmul($computed, self::MAX_DEVIATION, self::SCALE), self::SCALE) <= 0;
    }
}
