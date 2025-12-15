<?php

namespace Nip\Php\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nip\Php\Enums\PhpVersion;

class InstallPhpVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => [
                'required',
                'string',
                Rule::in(array_map(fn ($v) => $v->version(), PhpVersion::cases())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Please select a PHP version to install.',
            'version.in' => 'The selected PHP version is not valid.',
        ];
    }
}
