<?php

namespace Nip\Domain\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Domain\Enums\CertificateType;
use Nip\Site\Models\Site;

class StoreCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site && Gate::allows('update', $site->server);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CertificateType::class)],
            'domains' => ['required', 'array', 'min:1'],
            'domains.*' => [
                'required',
                'string',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]$/',
            ],
            'certificate' => ['required_if:type,existing', 'string'],
            'private_key' => ['required_if:type,existing', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Please select a certificate type.',
            'domains.required' => 'Please provide at least one domain.',
            'domains.min' => 'Please provide at least one domain.',
            'domains.*.regex' => 'Invalid domain format.',
            'certificate.required_if' => 'Certificate is required for existing certificates.',
            'private_key.required_if' => 'Private key is required for existing certificates.',
        ];
    }
}
