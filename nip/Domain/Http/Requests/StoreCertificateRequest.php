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
        $rules = [
            'type' => ['required', Rule::enum(CertificateType::class)],
        ];

        $type = $this->input('type');

        // Let's Encrypt: requires domain and options
        if ($type === CertificateType::LetsEncrypt->value) {
            $rules['domain'] = ['required', 'string'];
            $rules['verification_method'] = ['required', 'in:http,dns'];
            $rules['key_algorithm'] = ['required', 'in:ecdsa,rsa'];
            $rules['isrg_root_chain'] = ['boolean'];

            // DNS verification requires pre-generated ACME subdomains
            if ($this->input('verification_method') === 'dns') {
                $rules['acme_subdomains'] = [
                    'required',
                    'array',
                    'min:1',
                    function ($attribute, $value, $fail) {
                        foreach ($value as $domain => $subdomain) {
                            if (! is_string($subdomain) || ! preg_match('/^verify-[a-z0-9]+$/', $subdomain)) {
                                $fail("Invalid ACME subdomain format for {$domain}.");
                            }
                        }
                    },
                ];
            }
        }

        // Existing certificate: requires certificate and private key
        if ($type === CertificateType::Existing->value) {
            $rules['domain'] = ['required', 'string'];
            $rules['certificate'] = ['required', 'string'];
            $rules['private_key'] = ['required', 'string'];
            $rules['auto_activate'] = ['boolean'];
        }

        // CSR: requires all CSR fields
        if ($type === CertificateType::Csr->value) {
            $rules['domain'] = ['required', 'string'];
            $rules['sans'] = ['nullable', 'string'];
            $rules['csr_country'] = ['required', 'string', 'size:2'];
            $rules['csr_state'] = ['required', 'string', 'max:255'];
            $rules['csr_city'] = ['required', 'string', 'max:255'];
            $rules['csr_organization'] = ['required', 'string', 'max:255'];
            $rules['csr_department'] = ['required', 'string', 'max:255'];
        }

        // Clone: requires source certificate
        if ($type === CertificateType::Clone->value) {
            $rules['domain'] = ['required', 'string'];
            $rules['source_certificate_id'] = ['required', 'integer', 'exists:certificates,id'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Please select a certificate type.',
            'domain.required' => 'Please select a domain.',
            'verification_method.required' => 'Please select a verification method.',
            'verification_method.in' => 'Invalid verification method.',
            'key_algorithm.required' => 'Please select a public key algorithm.',
            'key_algorithm.in' => 'Invalid public key algorithm.',
            'certificate.required' => 'Certificate is required.',
            'private_key.required' => 'Private key is required.',
            'csr_country.required' => 'Country is required.',
            'csr_country.size' => 'Country must be a 2-letter code.',
            'csr_state.required' => 'State is required.',
            'csr_city.required' => 'City is required.',
            'csr_organization.required' => 'Organization is required.',
            'csr_department.required' => 'Department is required.',
            'source_certificate_id.required' => 'Please select a certificate to clone.',
            'source_certificate_id.exists' => 'The selected certificate does not exist.',
        ];
    }
}
