<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MealPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class GenerateMealPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', MealPlan::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'regenerate' => ['boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'regenerate' => $this->boolean('regenerate', false),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        // Eager rate-limit check to match test expectations
        $key = 'generate-meal-plan:' . $this->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'rate_limit' => "Too many meal plan generation attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $validator->after(function ($validator) {
            // Check for unique start_date constraint
            $data = method_exists($validator, 'getData') ? $validator->getData() : [];
            $startDate = $data['start_date'] ?? $this->input('start_date');

            // Normalize the date to ensure proper comparison
            $normalizedDate = \Carbon\Carbon::parse($startDate)->format('Y-m-d');

            $existingPlan = MealPlan::where('user_id', $this->user()->id)
                ->whereDate('start_date', $normalizedDate)
                ->exists();

            if ($existingPlan) {
                $validator->errors()->add('start_date', 'You already have a meal plan for this start date.');
            }
        });
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Hit the rate limiter
        $key = 'generate-meal-plan:' . $this->user()->id;
        RateLimiter::hit($key, 3600); // 1 hour
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
