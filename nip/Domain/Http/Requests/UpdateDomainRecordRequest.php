<?php

namespace Nip\Domain\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Enums\WildcardBehavior;
use Nip\Domain\Models\DomainRecord;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

class UpdateDomainRecordRequest extends FormRequest
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
        $domainRecord = $this->route('domainRecord');

        return [
            'www_redirect_type' => ['nullable', Rule::enum(WwwRedirectType::class)],
            'allow_wildcard' => ['nullable', 'boolean'],
            'wildcard_behavior' => ['nullable', Rule::enum(WildcardBehavior::class)],
            'redirect_target' => [
                'nullable',
                'string',
                'min:1',
                'max:255',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]$/',
                $domainRecord instanceof DomainRecord ? 'different:'.$domainRecord->name : 'string',
            ],
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

        $domainRecord = $this->route('domainRecord');
        $site = $this->route('site');

        if ($domainRecord instanceof DomainRecord && $domainRecord->isPrimary()) {
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
        return [];
    }
}
