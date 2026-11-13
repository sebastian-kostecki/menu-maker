<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesRecipeIngredients;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRecipeRequest extends FormRequest
{
    use ValidatesRecipeIngredients;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Same rules as StoreRecipeRequest but all fields optional for PATCH
        return [
            'name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|in:breakfast,supper,dinner',
            'instructions' => 'sometimes|required|string',
            'calories' => 'sometimes|required|numeric|min:0',
            'servings' => 'sometimes|required|integer|min:1',
        ] + $this->ingredientRules();
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Podaj nazwę przepisu.',
            'name.max' => 'Nazwa przepisu może mieć maksymalnie 255 znaków.',
            'category.required' => 'Wybierz kategorię przepisu.',
            'category.in' => 'Kategoria musi być śniadaniem, obiadem lub kolacją.',
            'instructions.required' => 'Podaj instrukcję przygotowania.',
            'calories.required' => 'Podaj kaloryczność.',
            'calories.numeric' => 'Kaloryczność musi być liczbą.',
            'calories.min' => 'Kaloryczność nie może być ujemna.',
            'servings.required' => 'Podaj liczbę porcji.',
            'servings.integer' => 'Liczba porcji musi być liczbą całkowitą.',
            'servings.min' => 'Liczba porcji musi wynosić co najmniej 1.',
        ] + $this->ingredientMessages();
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateUniqueIngredientNames($validator);
        });
    }
}
