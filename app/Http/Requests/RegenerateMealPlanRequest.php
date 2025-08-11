<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'regenerate' => ['required', 'accepted'],
            'force' => ['boolean', 'nullable'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'force' => $this->boolean('force', false),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mealPlan = $this->route('meal_plan');

            // Check if meal plan is currently processing
            if ($mealPlan->status === 'processing') {
                $validator->errors()->add('status', 'Cannot regenerate meal plan while it is being processed.');

                return;
            }

            // Only allow regeneration if status is 'done' or 'error'
            if (! in_array($mealPlan->status, ['done', 'error'])) {
                $validator->errors()->add('status', 'Can only regenerate completed or failed meal plans.');

                return;
            }

            // Check rate limit unless force flag is set
            if (! $this->input('force')) {
                $key = 'regenerate-meal-plan:'.$this->user()->id;

                if (RateLimiter::tooManyAttempts($key, 5)) {
                    $seconds = RateLimiter::availableIn($key);
                    throw ValidationException::withMessages([
                        'rate_limit' => "Too many regeneration attempts. Try again in {$seconds} seconds or use force flag.",
                    ]);
                }
            }
        });
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Hit the rate limiter unless force flag is set
        if (! $this->input('force')) {
            $key = 'regenerate-meal-plan:'.$this->user()->id;
            RateLimiter::hit($key, 3600); // 1 hour
        }
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
