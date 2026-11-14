<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling\Drivers;

use App\Services\IngredientScaling\RefinementItem;

/**
 * The prompt and JSON schema both real drivers send. Carries only ingredient names, unit codes and quantities.
 */
final class RefinementPrompt
{
    public const SYSTEM = 'You round scaled recipe ingredient amounts to practical kitchen amounts. '
        .'For every item return its key unchanged and a quantity in the same unit. '
        .'Use whole numbers for pieces (szt), sensible round numbers for grams and millilitres, '
        .'and never move a quantity more than 25% away from the given one. Never change a unit.';

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'quantity' => ['type' => 'number'],
                        ],
                        'required' => ['key', 'quantity'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['items'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  list<RefinementItem>  $items
     */
    public static function userMessage(array $items): string
    {
        return json_encode(
            array_map(static fn (RefinementItem $item): array => [
                'key' => $item->key,
                'name' => $item->name,
                'unit' => $item->unitCode,
                'quantity' => $item->computedQuantity,
            ], $items),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
