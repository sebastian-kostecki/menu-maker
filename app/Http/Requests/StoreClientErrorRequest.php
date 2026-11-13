<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientErrorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'stack' => ['nullable', 'string', 'max:5000'],
            'url' => ['required', 'string', 'max:2000'],
            'source' => ['required', Rule::in(['vue', 'window', 'promise'])],
        ];
    }
}
