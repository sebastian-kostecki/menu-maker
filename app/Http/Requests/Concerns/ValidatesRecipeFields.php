<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait ValidatesRecipeFields
{
    /**
     * Rules for the recipe's own (non-ingredient) fields.
     *
     * @return array<string, string>
     */
    protected function recipeFieldRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'required|in:breakfast,supper,dinner',
            'instructions' => 'required|string',
            'calories' => 'required|numeric|min:50',
            'servings' => 'required|integer|min:1',
        ];
    }

    /**
     * Polish messages for the recipe field rules.
     *
     * @return array<string, string>
     */
    protected function recipeFieldMessages(): array
    {
        return [
            'name.required' => 'Podaj nazwę przepisu.',
            'name.max' => 'Nazwa przepisu może mieć maksymalnie 255 znaków.',
            'category.required' => 'Wybierz kategorię przepisu.',
            'category.in' => 'Kategoria musi być śniadaniem, obiadem lub kolacją.',
            'instructions.required' => 'Podaj instrukcję przygotowania.',
            'calories.required' => 'Podaj kaloryczność.',
            'calories.numeric' => 'Kaloryczność musi być liczbą.',
            'calories.min' => 'Kaloryczność całkowita musi wynosić co najmniej 50 kcal.',
            'servings.required' => 'Podaj liczbę porcji.',
            'servings.integer' => 'Liczba porcji musi być liczbą całkowitą.',
            'servings.min' => 'Liczba porcji musi wynosić co najmniej 1.',
        ];
    }
}
