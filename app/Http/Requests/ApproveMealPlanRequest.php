<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ApproveMealPlanRequest extends FormRequest
{
    public const NOT_DRAFT_MESSAGE = 'Zatwierdzić można tylko szkic jadłospisu.';

    public const EMPTY_DRAFT_MESSAGE = 'Szkic jest pusty. Wylosuj przepisy ponownie.';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('meal_plan'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        // Eager rate-limit check: approval is the only path into the AI call.
        $key = $this->rateLimitKey();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'rate_limit' => "Zbyt wiele prób generowania. Spróbuj ponownie za {$seconds} s.",
            ]);
        }

        $validator->after(function ($validator): void {
            $mealPlan = $this->route('meal_plan');

            if ($mealPlan->status !== 'draft') {
                $validator->errors()->add('status', self::NOT_DRAFT_MESSAGE);

                return;
            }

            if (! $mealPlan->meals()->exists()) {
                $validator->errors()->add('meals', self::EMPTY_DRAFT_MESSAGE);
            }
        });
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        RateLimiter::hit($this->rateLimitKey(), 3600); // 1 hour
    }

    private function rateLimitKey(): string
    {
        return 'generate-meal-plan:'.$this->user()->id;
    }
}
