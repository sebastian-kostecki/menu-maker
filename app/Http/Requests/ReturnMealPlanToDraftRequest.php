<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReturnMealPlanToDraftRequest extends FormRequest
{
    /**
     * Statuses a plan may return to draft from.
     *
     * @var list<string>
     */
    public const RETURNABLE_STATUSES = ['done', 'error'];

    public const NOT_RETURNABLE_MESSAGE = 'Do szkicu można wrócić tylko z gotowego lub nieudanego jadłospisu.';

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
        $validator->after(function ($validator): void {
            if (! in_array($this->route('meal_plan')->status, self::RETURNABLE_STATUSES, true)) {
                $validator->errors()->add('status', self::NOT_RETURNABLE_MESSAGE);
            }
        });
    }
}
