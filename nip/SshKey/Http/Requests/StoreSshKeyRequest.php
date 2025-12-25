<?php

namespace Nip\SshKey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Nip\SshKey\Models\SshKey;
use Nip\SshKey\Rules\SshPublicKeyRule;

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
            'public_key' => ['required', new SshPublicKeyRule],
            'unix_user_id' => ['required', 'exists:unix_users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $publicKey = $this->input('public_key');
            $serverId = $this->route('server')->id;
            $unixUserId = $this->input('unix_user_id');

            if ($publicKey && $serverId && $unixUserId) {
                $fingerprint = SshKey::generateFingerprint($publicKey);

                $exists = SshKey::where('server_id', $serverId)
                    ->where('unix_user_id', $unixUserId)
                    ->where('fingerprint', $fingerprint)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('public_key', 'This SSH key already exists for this user.');
                }
            }
        });
    }
}
