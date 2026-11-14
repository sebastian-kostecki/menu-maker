<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

/**
 * Structural validation of a decoded `{items: [{key, quantity}]}` AI response, shared by the real drivers.
 */
final class RefinementResponseParser
{
    /**
     * @param  list<RefinementItem>  $items
     * @return array<string, string>
     *
     * @throws RefinementFailedException
     */
    public static function parse(mixed $decoded, array $items): array
    {
        if (! is_array($decoded) || ! isset($decoded['items']) || ! is_array($decoded['items'])) {
            throw new RefinementFailedException('AI response has no items list.');
        }

        $expected = array_fill_keys(array_map(static fn (RefinementItem $item): string => $item->key, $items), true);
        $proposals = [];

        foreach ($decoded['items'] as $entry) {
            if (! is_array($entry) || ! isset($entry['key'], $entry['quantity']) || ! is_string($entry['key'])) {
                throw new RefinementFailedException('AI response item is malformed.');
            }

            if (! isset($expected[$entry['key']])) {
                throw new RefinementFailedException('AI response contains an unknown key.');
            }

            if (isset($proposals[$entry['key']])) {
                throw new RefinementFailedException('AI response contains a duplicate key.');
            }

            if (! is_int($entry['quantity']) && ! is_float($entry['quantity']) && ! (is_string($entry['quantity']) && is_numeric($entry['quantity']))) {
                throw new RefinementFailedException('AI response contains a non-numeric quantity.');
            }

            $proposals[$entry['key']] = number_format((float) $entry['quantity'], 4, '.', '');
        }

        if (count($proposals) !== count($expected)) {
            throw new RefinementFailedException('AI response is missing keys.');
        }

        return $proposals;
    }
}
