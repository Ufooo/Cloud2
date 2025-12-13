<?php

namespace Nip\Database\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatabaseRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $serverId = $this->route('server')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique('databases')->where('server_id', $serverId),
            ],
            'user' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique('database_users', 'username')->where('server_id', $serverId),
            ],
            'password' => [
                'nullable',
                'required_with:user',
                'string',
                'min:8',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Database name must start with a letter and contain only letters, numbers, and underscores.',
            'name.unique' => 'A database with this name already exists on this server.',
            'user.regex' => 'Username must start with a letter and contain only letters, numbers, and underscores.',
            'user.unique' => 'A database user with this username already exists on this server.',
            'password.required_with' => 'Password is required when creating a user.',
        ];
    }
}
