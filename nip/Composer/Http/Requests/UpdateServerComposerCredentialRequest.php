<?php

namespace Nip\Composer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nip\Server\Models\Server;

class UpdateServerComposerCredentialRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Server $server */
        $server = $this->route('server');

        return [
            'user' => [
                'required',
                'string',
                'max:255',
                Rule::exists('unix_users', 'username')->where('server_id', $server->id),
            ],
            'repository' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1000'],
        ];
    }
}
