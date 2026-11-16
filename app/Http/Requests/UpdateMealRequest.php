<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Meal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMealRequest extends FormRequest
{
    public const INVALID_RECIPE_MESSAGE = 'Wybierz jeden ze swoich przepisów z tej kategorii.';

    public const NOT_DRAFT_MESSAGE = 'Przepisy można zmieniać tylko w szkicu jadłospisu.';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('meal'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Meal $meal */
        $meal = $this->route('meal');

        return [
            'recipe_id' => [
                'required',
                'integer',
                Rule::exists('recipes', 'id')
                    ->where('user_id', $meal->mealPlan->user_id)
                    ->where('category', $meal->meal_category)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipe_id.exists' => self::INVALID_RECIPE_MESSAGE,
        ];
    }

    /**
     * Get the validation callbacks run after the rules.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                /** @var Meal $meal */
                $meal = $this->route('meal');

                if ($meal->mealPlan->status !== 'draft') {
                    $validator->errors()->add('status', self::NOT_DRAFT_MESSAGE);
                }
            },
        ];
    }
}
