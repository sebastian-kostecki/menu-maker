<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

use App\Models\MealPlan;

/**
 * Turns `generation_meta.scaling` into the Polish notices shown on screen and in the PDF.
 */
final class ScalingNotices
{
    /**
     * @return array{status: ?string, notices: list<string>}
     */
    public static function for(MealPlan $mealPlan): array
    {
        if ($mealPlan->status !== 'done') {
            return ['status' => null, 'notices' => []];
        }

        /** @var array<string, mixed> $meta */
        $meta = $mealPlan->generation_meta ?? [];
        $scaling = is_array($meta['scaling'] ?? null) ? $meta['scaling'] : [];
        $status = is_string($scaling['status'] ?? null) ? $scaling['status'] : null;
        $unscaledRecipes = self::names($scaling['unscaled_recipes'] ?? null);
        $membersWithoutBody = self::names($scaling['members_without_body'] ?? null);

        $notices = [];

        if ($status === ScalingOutcome::STATUS_AS_WRITTEN) {
            $notices[] = 'Dodaj członków rodziny, aby przeliczyć składniki. Ilości są podane jak w przepisach.';
        }

        if ($status === ScalingOutcome::STATUS_COMPUTED) {
            $notices[] = 'Nie udało się dopracować ilości przez AI — pokazujemy ilości przeliczone automatycznie.';
        }

        if ($unscaledRecipes !== []) {
            $notices[] = 'Przepisy z brakującą lub zbyt niską kalorycznością nie zostały przeliczone: '.implode(', ', $unscaledRecipes).'.';
        }

        if ($membersWithoutBody !== []) {
            $notices[] = 'Uzupełnij wagę i wzrost, aby dokładniej przeliczyć porcje: '.implode(', ', $membersWithoutBody).'.';
        }

        $version = $meta['version'] ?? null;

        if ($version === null || $version === 'random-v1') {
            $notices[] = 'Ten jadłospis powstał przed przeliczaniem składników — zregeneruj go, aby dopasować ilości do rodziny.';
        }

        return ['status' => $status, 'notices' => $notices];
    }

    /**
     * @return list<string>
     */
    private static function names(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
