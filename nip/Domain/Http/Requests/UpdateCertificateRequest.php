<?php

namespace Nip\Domain\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCertificateRequest extends FormRequest
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
            'certificate' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'certificate.required' => 'The certificate is required.',
        ];
    }
}
