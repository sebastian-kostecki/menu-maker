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

            if ($mealPlan->status === 'processing') {
                $validator->errors()->add('status', 'Nie można regenerować jadłospisu w trakcie generowania.');

                return;
            }

            if (! in_array($mealPlan->status, ['done', 'error'])) {
                $validator->errors()->add('status', 'Regenerować można tylko gotowy lub nieudany jadłospis.');

                return;
            }

            $generator = app(MealPlanGenerator::class);
            $missing = $generator->missingRecipesPerCategory($this->user()->id);

            foreach ($generator->shortfallMessages($missing) as $message) {
                $validator->errors()->add('recipes', $message);
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
