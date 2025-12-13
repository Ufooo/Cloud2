<?php

namespace Nip\Redirect\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Redirect\Enums\RedirectType;
use Nip\Site\Models\Site;

class UpdateRedirectRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Site $site */
        $site = $this->route('site');

        return Gate::allows('update', $site->server);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'string', 'max:255'],
            'to' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(RedirectType::class)],
        ];
    }
}
