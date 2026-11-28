<?php

declare(strict_types=1);

namespace App\Services\RecipeImport;

use App\Http\Requests\Concerns\ValidatesRecipeFields;
use App\Http\Requests\Concerns\ValidatesRecipeIngredients;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Validates an already structured recipe with the recipe form's limits and stores it for one owner,
 * refusing a name the owner already has.
 */
final class StructuredRecipeImporter
{
    use ValidatesRecipeFields;
    use ValidatesRecipeIngredients;

    /**
     * The largest value the decimal(10,2) calories column holds.
     */
    private const MAX_CALORIES = '99999999.99';

    public function __construct(private readonly RecipeImporter $importer) {}

    /**
     * @param  array<string, mixed>  $input  name, category, calories (total kcal), servings, instructions,
     *                                       ingredients (name, quantity, unit code) and an optional source_url.
     *
     * @throws ValidationException
     */
    public function import(User $user, array $input): Recipe
    {
        $data = $this->validate($this->normalize($input));

        $existing = $this->importer->findFor($user, $data['name']);

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'name' => "Masz już przepis „{$existing->name}”: ".route('recipes.show', $existing),
            ]);
        }

        $sourceUrl = $data['source_url'] ?? null;

        return $this->importer->import($user, new ParsedRecipe(
            name: $data['name'],
            category: $data['category'],
            instructions: $sourceUrl === null || $sourceUrl === ''
                ? $data['instructions']
                : "{$data['instructions']}\n\nŹródło: {$sourceUrl}",
            calories: $this->decimal($data['calories']),
            servings: (int) $data['servings'],
            ingredients: array_map(
                fn (array $row): ParsedIngredient => new ParsedIngredient(
                    $row['name'],
                    $this->decimal($row['quantity']),
                    $row['unit'],
                ),
                array_values($data['ingredients']),
            ),
            warnings: [],
            errors: [],
        ));
    }

    /**
     * Does for this input what the web middleware and the importer expect of form input: trimmed texts
     * and names with single spaces.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        foreach (['instructions', 'source_url'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = Str::trim($input[$key]);
            }
        }

        if (is_string($input['name'] ?? null)) {
            $input['name'] = Ingredient::normalizeName($input['name']);
        }

        if (is_array($input['ingredients'] ?? null)) {
            foreach ($input['ingredients'] as $index => $row) {
                if (is_array($row) && is_string($row['name'] ?? null)) {
                    $input['ingredients'][$index]['name'] = Ingredient::normalizeName($row['name']);
                }
            }
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(array $input): array
    {
        $rules = [
            ...$this->recipeFieldRules(),
            ...$this->ingredientRowRules(),
            'ingredients.*.unit' => 'required|in:'.implode(',', Unit::ALLOWED_CODES),
            'source_url' => 'nullable|string|max:2048|url:http,https',
        ];
        // Only here: a JSON number can be far larger than anything typed into the form.
        $rules['calories'] .= '|max:'.self::MAX_CALORIES;

        $unitMessage = 'Jednostka składnika musi być jedną z: '.implode(', ', Unit::ALLOWED_CODES).'.';

        $validator = Validator::make($input, $rules, [
            ...$this->recipeFieldMessages(),
            ...$this->ingredientMessages(),
            'calories.max' => 'Kaloryczność jest zbyt duża.',
            'ingredients.*.unit.required' => $unitMessage,
            'ingredients.*.unit.in' => $unitMessage,
        ]);

        $validator->after(function (ValidatorInstance $validator): void {
            $this->validateUniqueIngredientNames($validator);
        });

        return $validator->validate();
    }

    /**
     * A validated positive number rounded half-up to two decimals.
     *
     * @return numeric-string
     */
    private function decimal(int|float|string $value): string
    {
        // Fixed-point first: bcmath rejects the exponent notation a float or a numeric string may use.
        return bcadd(sprintf('%.10F', (float) $value), '0.005', 2);
    }
}
