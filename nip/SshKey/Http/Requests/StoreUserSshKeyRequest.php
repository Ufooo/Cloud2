<?php

namespace Nip\SshKey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Nip\SshKey\Models\UserSshKey;
use Nip\SshKey\Rules\SshPublicKeyRule;

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
            'public_key' => ['required', new SshPublicKeyRule],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $publicKey = $this->input('public_key');
            $userId = auth()->id();

            if ($publicKey && $userId) {
                $fingerprint = UserSshKey::generateFingerprint($publicKey);

                $exists = UserSshKey::where('user_id', $userId)
                    ->where('fingerprint', $fingerprint)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('public_key', 'This SSH key already exists on your account.');
                }
            }
        });
    }
}
