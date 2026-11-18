<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Meal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MoveMealRequest extends FormRequest
{
    public const TARGET_INVALID_MESSAGE = 'Posiłek można przenieść tylko w obrębie tego jadłospisu.';

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
            'target_meal_id' => [
                'required',
                'integer',
                Rule::exists('meals', 'id')->where('meal_plan_id', $meal->meal_plan_id),
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
            'target_meal_id.required' => self::TARGET_INVALID_MESSAGE,
            'target_meal_id.integer' => self::TARGET_INVALID_MESSAGE,
            'target_meal_id.exists' => self::TARGET_INVALID_MESSAGE,
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
                    $validator->errors()->add('status', UpdateMealRequest::NOT_DRAFT_MESSAGE);
                }
            },
        ];
    }
}
