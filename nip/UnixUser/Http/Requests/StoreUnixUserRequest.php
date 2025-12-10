<?php

namespace Nip\UnixUser\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Server\Models\Server;

class StoreUnixUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('server'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Server $server */
        $server = $this->route('server');

        return [
            'username' => [
                'required',
                'string',
                'min:1',
                'max:32',
                'regex:/^[a-z_][a-z0-9_-]*$/',
                Rule::unique('unix_users', 'username')->where('server_id', $server->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'The username must start with a lowercase letter or underscore, and contain only lowercase letters, numbers, underscores, and hyphens.',
            'username.unique' => 'This username already exists on this server.',
        ];
    }
}
