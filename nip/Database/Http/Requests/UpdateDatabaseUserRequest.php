<?php

namespace Nip\Database\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDatabaseUserRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $serverId = $this->route('server')?->id;

        return [
            'password' => [
                'nullable',
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
            'databases.*.exists' => 'One or more selected databases do not exist on this server.',
        ];
    }
}
