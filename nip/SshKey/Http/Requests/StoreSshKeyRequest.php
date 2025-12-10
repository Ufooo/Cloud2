<?php

namespace Nip\SshKey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Nip\SshKey\Models\SshKey;

class StoreSshKeyRequest extends FormRequest
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
            'unix_user_id' => ['nullable', 'exists:unix_users,id'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $publicKey = $this->input('public_key');
            $serverId = $this->route('server')->id;

            if ($publicKey && $serverId) {
                $fingerprint = SshKey::generateFingerprint($publicKey);

                $exists = SshKey::where('server_id', $serverId)
                    ->where('fingerprint', $fingerprint)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('public_key', 'This SSH key already exists on this server.');
                }
            }
        });
    }
}
