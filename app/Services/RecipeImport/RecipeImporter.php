<?php

declare(strict_types=1);

namespace App\Services\RecipeImport;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stores parsed recipes for one owner the same way RecipeController::store does.
 */
final class RecipeImporter
{
    /**
     * Whether the owner already has a (non-deleted) recipe with this name, ignoring case.
     */
    public function existsFor(User $user, string $name): bool
    {
        return Recipe::query()
            ->forUser($user->id)
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->exists();
    }

    public function import(User $user, ParsedRecipe $parsed): Recipe
    {
        if (! $parsed->isImportable()) {
            throw new InvalidArgumentException("Recipe \"{$parsed->name}\" has errors and cannot be imported.");
        }

        /** @var array<string, int> $unitIds */
        $unitIds = Unit::query()->whereIn('code', Unit::ALLOWED_CODES)->pluck('id', 'code')->all();

        return DB::transaction(function () use ($user, $parsed, $unitIds): Recipe {
            /** @var Recipe $recipe */
            $recipe = $user->recipes()->create([
                'name' => $parsed->name,
                'category' => $parsed->category,
                'instructions' => $parsed->instructions,
                'calories' => $parsed->calories,
                'servings' => $parsed->servings,
            ]);

            $pivotData = [];

            foreach ($parsed->ingredients as $ingredient) {
                $pivotData[Ingredient::resolveIdByName($ingredient->name)] = [
                    'quantity' => $ingredient->quantity,
                    'unit_id' => $unitIds[$ingredient->unitCode],
                ];
            }

            $recipe->ingredients()->sync($pivotData);

            return $recipe;
        });
    }
}
