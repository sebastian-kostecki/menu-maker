<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'required|in:breakfast,supper,dinner',
            'instructions' => 'required|string',
            'calories' => 'required|numeric|min:0',
            'servings' => 'required|integer|min:1',
            'ingredients' => 'sometimes|array',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0.01',
            'ingredients.*.unit_id' => 'required|exists:units,id',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Recipe name is required.',
            'name.max' => 'Recipe name cannot exceed 255 characters.',
            'category.required' => 'Recipe category is required.',
            'category.in' => 'Recipe category must be breakfast, lunch, or dinner.',
            'instructions.required' => 'Recipe instructions are required.',
            'calories.required' => 'Calories field is required.',
            'calories.numeric' => 'Calories must be a number.',
            'calories.min' => 'Calories cannot be negative.',
            'servings.required' => 'Servings field is required.',
            'servings.integer' => 'Servings must be a whole number.',
            'servings.min' => 'Servings must be at least 1.',
            'ingredients.array' => 'Ingredients must be an array.',
            'ingredients.*.ingredient_id.required' => 'Each ingredient must have an ingredient ID.',
            'ingredients.*.ingredient_id.exists' => 'The selected ingredient does not exist.',
            'ingredients.*.quantity.required' => 'Each ingredient must have a quantity.',
            'ingredients.*.quantity.numeric' => 'Ingredient quantity must be a number.',
            'ingredients.*.quantity.min' => 'Ingredient quantity must be at least 0.01.',
            'ingredients.*.unit_id.required' => 'Each ingredient must have a unit.',
            'ingredients.*.unit_id.exists' => 'The selected unit does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure ingredients array doesn't contain duplicates
        if ($this->has('ingredients') && is_array($this->ingredients)) {
            $uniqueIngredients = collect($this->ingredients)
                ->unique('ingredient_id')
                ->values()
                ->all();

            $this->merge(['ingredients' => $uniqueIngredients]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for duplicate ingredients
            if ($this->has('ingredients') && is_array($this->ingredients)) {
                $ingredientIds = collect($this->ingredients)->pluck('ingredient_id');
                $duplicates = $ingredientIds->duplicates();

                if ($duplicates->isNotEmpty()) {
                    $validator->errors()->add('ingredients', 'Duplicate ingredients are not allowed.');
                }
            }
        });
    }
}
