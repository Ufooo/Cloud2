<?php

namespace Nip\UserSshKey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserSshKeyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'public_key' => [
                'required',
                'string',
                'regex:/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|ecdsa-sha2-nistp521)\s+[A-Za-z0-9+\/=]+/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'public_key.regex' => 'The public key must be a valid SSH public key (ssh-rsa, ssh-ed25519, or ecdsa).',
        ];
    }
}
