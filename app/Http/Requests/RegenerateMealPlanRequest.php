<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\MealPlanGenerator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RegenerateMealPlanRequest extends FormRequest
{
    /**
     * Statuses a plan may be redrawn from.
     *
     * @var list<string>
     */
    public const REGENERATABLE_STATUSES = ['draft'];

    public const NOT_REGENERATABLE_MESSAGE = 'Losować ponownie można tylko szkic jadłospisu.';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $mealPlan = $this->route('meal_plan');

        return Gate::allows('update', $mealPlan);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'regenerate' => ['required', 'accepted'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mealPlan = $this->route('meal_plan');

            if (! in_array($mealPlan->status, self::REGENERATABLE_STATUSES, true)) {
                $validator->errors()->add('status', self::NOT_REGENERATABLE_MESSAGE);

                return;
            }

            if (! app(MealPlanGenerator::class)->hasAnyRecipe($this->user()->id)) {
                $validator->errors()->add('recipes', MealPlanGenerator::NO_RECIPES_MESSAGE);
            }
        });
    }

    /**
     * Get validated data with user ID injected.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        $validated['user_id'] = $this->user()->id;

        return $validated;
    }
}
