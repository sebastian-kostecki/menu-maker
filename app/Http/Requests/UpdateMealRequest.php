<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Meal;
use App\Services\MealLinks\MealLinkRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;

class UpdateMealRequest extends FormRequest
{
    public const INVALID_RECIPE_MESSAGE = 'Wybierz jeden ze swoich przepisów.';

    public const LINK_NOT_ALLOWED_MESSAGE = 'Tego posiłku nie można połączyć z poprzednim dniem.';

    public const EXACTLY_ONE_MESSAGE = 'Podaj dokładnie jedno z pól: recipe_id albo from_meal_id.';

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
                Rule::when(fn (Fluent $input): bool => ! array_key_exists('from_meal_id', $input->all()), ['present']),
                'nullable',
                'integer',
                Rule::exists('recipes', 'id')
                    ->where('user_id', $meal->mealPlan->user_id)
                    ->whereNull('deleted_at'),
            ],
            'from_meal_id' => [
                'missing_with:recipe_id',
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
            'recipe_id.exists' => self::INVALID_RECIPE_MESSAGE,
            'from_meal_id.missing_with' => self::EXACTLY_ONE_MESSAGE,
            'from_meal_id.exists' => self::LINK_NOT_ALLOWED_MESSAGE,
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

                $fromMealId = $validator->getData()['from_meal_id'] ?? null;

                if ($validator->errors()->has('from_meal_id') || ! is_int($fromMealId)) {
                    return;
                }

                $from = Meal::query()->find($fromMealId);

                if ($from === null || ! app(MealLinkRules::class)->canFeed($from, $meal)) {
                    $validator->errors()->add('from_meal_id', self::LINK_NOT_ALLOWED_MESSAGE);
                }
            },
        ];
    }
}
