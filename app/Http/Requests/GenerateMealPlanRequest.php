<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MealPlan;
use App\Services\MealPlanGenerator;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GenerateMealPlanRequest extends FormRequest
{
    public const DUPLICATE_START_DATE_MESSAGE = 'Masz już jadłospis zaczynający się tego dnia.';

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for unique start_date constraint
            $data = method_exists($validator, 'getData') ? $validator->getData() : [];
            $startDate = $data['start_date'] ?? $this->input('start_date');

            // Normalize the date to ensure proper comparison
            $normalizedDate = Carbon::parse($startDate)->format('Y-m-d');

            $existingPlan = MealPlan::where('user_id', $this->user()->id)
                ->whereDate('start_date', $normalizedDate)
                ->exists();

            if ($existingPlan) {
                $validator->errors()->add('start_date', self::DUPLICATE_START_DATE_MESSAGE);
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
