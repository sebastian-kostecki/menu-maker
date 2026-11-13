<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesRecipeIngredients
{
    /**
     * Rules for the required ingredient rows, each identified by name.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function ingredientRules(): array
    {
        return [
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],
            'ingredients.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'ingredients.*.unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->whereIn('code', Unit::ALLOWED_CODES),
            ],
        ];
    }

    /**
     * Polish messages for the ingredient rules.
     *
     * @return array<string, string>
     */
    protected function ingredientMessages(): array
    {
        return [
            'ingredients.required' => 'Dodaj co najmniej jeden składnik.',
            'ingredients.array' => 'Składniki muszą być listą.',
            'ingredients.min' => 'Dodaj co najmniej jeden składnik.',
            'ingredients.*.name.required' => 'Podaj nazwę składnika.',
            'ingredients.*.name.string' => 'Nazwa składnika musi być tekstem.',
            'ingredients.*.name.max' => 'Nazwa składnika może mieć maksymalnie 255 znaków.',
            'ingredients.*.quantity.required' => 'Podaj ilość składnika.',
            'ingredients.*.quantity.numeric' => 'Ilość składnika musi być liczbą.',
            'ingredients.*.quantity.min' => 'Ilość składnika musi wynosić co najmniej 0,01.',
            'ingredients.*.quantity.max' => 'Ilość składnika jest zbyt duża.',
            'ingredients.*.unit_id.required' => 'Wybierz jednostkę składnika.',
            'ingredients.*.unit_id.integer' => 'Wybrana jednostka jest nieprawidłowa.',
            'ingredients.*.unit_id.exists' => 'Wybrana jednostka jest nieprawidłowa.',
        ];
    }

    /**
     * Adds an error to every row after the first whose normalized name repeats (case-insensitive).
     */
    protected function validateUniqueIngredientNames(Validator $validator): void
    {
        $rows = $validator->getData()['ingredients'] ?? null;

        if (! is_array($rows)) {
            return;
        }

        $seen = [];

        foreach (array_values($rows) as $index => $row) {
            $name = is_array($row) ? ($row['name'] ?? null) : null;

            if (! is_string($name) || Ingredient::normalizeName($name) === '') {
                continue;
            }

            $key = mb_strtolower(Ingredient::normalizeName($name));

            if (isset($seen[$key])) {
                $validator->errors()->add("ingredients.{$index}.name", 'Ten składnik już jest na liście.');
            }

            $seen[$key] = true;
        }
    }
}
