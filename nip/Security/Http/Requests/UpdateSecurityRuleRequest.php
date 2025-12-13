<?php

namespace Nip\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Nip\Site\Models\Site;

class UpdateSecurityRuleRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:1', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
            'credentials' => ['sometimes', 'array', 'min:1'],
            'credentials.*.username' => ['required_with:credentials', 'string', 'min:1', 'max:255'],
            'credentials.*.password' => ['required_with:credentials', 'string', 'min:1', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credentials.min' => 'At least one credential is required.',
            'credentials.*.username.required_with' => 'Username is required.',
            'credentials.*.password.required_with' => 'Password is required.',
        ];
    }
}
