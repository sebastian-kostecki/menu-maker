<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class FamilyMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'weight_kg' => [Rule::requiredIf($this->isAdult(...)), 'nullable', 'numeric', 'between:30,300'],
            'height_cm' => [Rule::requiredIf($this->isAdult(...)), 'nullable', 'numeric', 'between:100,250'],
        ];
    }

    /**
     * Whether the submitted birth date makes the member 19 or older today.
     */
    private function isAdult(): bool
    {
        $birthDate = $this->input('birth_date');

        if (! is_string($birthDate) || $birthDate === '') {
            return false;
        }

        try {
            return Carbon::parse($birthDate)->startOfDay()->diffInYears(today(), false) >= 19;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'The first name field is required.',
            'first_name.max' => 'The first name may not be greater than 255 characters.',
            'birth_date.required' => 'The birth date field is required.',
            'birth_date.date' => 'The birth date must be a valid date.',
            'birth_date.before' => 'The birth date must be before today.',
            'gender.required' => 'The gender field is required.',
            'gender.in' => 'The selected gender is invalid.',
            'weight_kg.required' => 'Podaj wagę (wymagana od 19 lat).',
            'weight_kg.numeric' => 'Waga musi wynosić od 30 do 300 kg.',
            'weight_kg.between' => 'Waga musi wynosić od 30 do 300 kg.',
            'height_cm.required' => 'Podaj wzrost (wymagany od 19 lat).',
            'height_cm.numeric' => 'Wzrost musi wynosić od 100 do 250 cm.',
            'height_cm.between' => 'Wzrost musi wynosić od 100 do 250 cm.',
        ];
    }
}
