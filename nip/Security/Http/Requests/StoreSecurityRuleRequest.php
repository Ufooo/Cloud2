<?php

namespace Nip\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Nip\Security\Http\Requests\Concerns\HasSecurityRulePathRules;
use Nip\Site\Models\Site;

class StoreSecurityRuleRequest extends FormRequest
{
    use HasSecurityRulePathRules;

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
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'path' => $this->pathRules(),
            'credentials' => ['required', 'array', 'min:1'],
            'credentials.*.username' => ['required', 'string', 'min:1', 'max:255', 'regex:/^[^:\s\p{Z}\p{Cc}]+$/uD'],
            'credentials.*.password' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credentials.required' => 'At least one credential is required.',
            'credentials.min' => 'At least one credential is required.',
            'credentials.*.username.required' => 'Username is required.',
            'credentials.*.password.required' => 'Password is required.',
            'credentials.*.username.regex' => 'The username may not contain a colon, spaces or line breaks.',
            ...$this->pathMessages(),
        ];
    }
}
