<?php

namespace Nip\Domain\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

class StoreDomainRecordRequest extends FormRequest
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
        $site = $this->route('site');

        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:255',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]$/',
                Rule::unique('domain_records')->where(function ($query) use ($site) {
                    return $query->where('site_id', $site->id);
                }),
            ],
            'type' => ['required', Rule::enum(DomainRecordType::class)],
            'redirect_target' => [
                'required_if:type,'.DomainRecordType::Redirect->value,
                'nullable',
                'string',
                'min:1',
                'max:255',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]$/',
                'different:name',
            ],
            'www_redirect_type' => ['nullable', Rule::enum(WwwRedirectType::class)],
            'allow_wildcard' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateToPrimaryRedirect($validator);
        });
    }

    protected function validateToPrimaryRedirect(Validator $validator): void
    {
        if ($this->input('www_redirect_type') !== WwwRedirectType::ToPrimary->value) {
            return;
        }

        $site = $this->route('site');

        if ($this->input('type') === DomainRecordType::Primary->value) {
            $validator->errors()->add(
                'www_redirect_type',
                'Primary domain cannot redirect to itself.'
            );

            return;
        }

        $hasPrimary = $site->domainRecords()
            ->where('type', DomainRecordType::Primary->value)
            ->exists();

        if (! $hasPrimary) {
            $validator->errors()->add(
                'www_redirect_type',
                'A primary domain must exist before using redirect to primary.'
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a domain name.',
            'name.regex' => 'The domain format is invalid.',
            'name.unique' => 'This domain is already configured for this site.',
            'type.required' => 'Please select a domain type.',
            'redirect_target.required_if' => 'Please enter the target domain for the redirect.',
            'redirect_target.regex' => 'The redirect target format is invalid.',
            'redirect_target.different' => 'The redirect target must differ from the domain itself.',
        ];
    }
}
