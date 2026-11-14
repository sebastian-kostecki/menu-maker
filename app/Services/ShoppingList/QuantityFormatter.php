<?php

declare(strict_types=1);

namespace App\Services\ShoppingList;

use App\Models\Unit;
use NumberFormatter;

class QuantityFormatter
{
    /**
     * Format a dot-decimal quantity and a unit code as Polish text, e.g. "0.25" l as "0,25 l".
     */
    public function format(string $quantity, string $unitCode): string
    {
        $formatter = new NumberFormatter('pl_PL', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
        $formatter->setAttribute(NumberFormatter::GROUPING_USED, 0);

        $number = $formatter->format((float) $quantity);

        if ($number === false) {
            $number = str_replace('.', ',', $quantity);
        }

        return $number.' '.(Unit::LABELS[$unitCode] ?? $unitCode);
    }
}
