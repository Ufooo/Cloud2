<?php

namespace Nip\Composer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComposerCredentialRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'repository' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1000'],
        ];
    }
}
