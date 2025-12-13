<?php

namespace Nip\Database\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatabaseUserRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $serverId = $this->route('server')?->id;

        return [
            'username' => [
                'required',
                'string',
                'max:32',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique('database_users')->where('server_id', $serverId),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
            ],
            'databases' => [
                'nullable',
                'array',
            ],
            'databases.*' => [
                'integer',
                Rule::exists('databases', 'id')->where('server_id', $serverId),
            ],
            'readonly' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username must start with a letter and contain only letters, numbers, and underscores.',
            'username.unique' => 'A database user with this username already exists on this server.',
            'databases.*.exists' => 'One or more selected databases do not exist on this server.',
        ];
    }
}
