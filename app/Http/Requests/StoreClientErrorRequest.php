<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreClientErrorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reports come from unauthenticated clients and are written to the log, so
     * control characters (log forging) and the query string / hash (tokens,
     * e-mail addresses in the URL) are removed before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'message' => $this->toSingleLine($this->input('message')),
            'stack' => $this->toSingleLine($this->input('stack'), ' | '),
            'url' => $this->toSingleLine($this->pathOnly($this->input('url'))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:500'],
            'stack' => ['nullable', 'string', 'max:2000'],
            'url' => ['required', 'string', 'max:1000'],
            'source' => ['required', Rule::in(['vue', 'window', 'promise'])],
        ];
    }

    private function pathOnly(mixed $url): mixed
    {
        return is_string($url) ? Str::before(Str::before($url, '#'), '?') : $url;
    }

    private function toSingleLine(mixed $value, string $lineBreak = ' '): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = preg_replace('/\R/u', $lineBreak, $value) ?? '';

        return trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '');
    }
}
