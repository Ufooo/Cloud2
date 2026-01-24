<?php

namespace Nip\Site\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Php\Enums\PhpVersion;
use Nip\Server\Models\Server;
use Nip\Shared\Traits\HasSiteValidationMessages;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteType;
use Nip\Site\Enums\WwwRedirectType;

class StoreSiteRequest extends FormRequest
{
    use HasSiteValidationMessages;

    public function authorize(): bool
    {
        $serverId = $this->input('server_id');
        if (! $serverId) {
            return false;
        }

        $server = Server::find($serverId);

        return $server && Gate::allows('update', $server);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'server_id' => ['required', 'exists:servers,id'],
            'database_id' => [
                'nullable',
                Rule::exists('databases', 'id')->where('server_id', $this->input('server_id')),
            ],
            'database_user_id' => [
                'nullable',
                Rule::exists('database_users', 'id')->where('server_id', $this->input('server_id')),
            ],
            'domain' => [
                'required',
                'string',
                'min:1',
                'max:255',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]$/',
                Rule::unique('sites')->where(function ($query) {
                    return $query->where('server_id', $this->input('server_id'));
                }),
            ],
            'type' => ['required', Rule::enum(SiteType::class)],
            'www_redirect_type' => ['nullable', Rule::enum(WwwRedirectType::class)],
            'allow_wildcard' => ['nullable', 'boolean'],
            'user' => [
                'required',
                'string',
                Rule::exists('unix_users', 'username')->where('server_id', $this->input('server_id')),
            ],
            'root_directory' => ['nullable', 'string', 'max:255', 'regex:/^\//'],
            'web_directory' => ['nullable', 'string', 'max:255', 'regex:/^\//'],
            'php_version' => ['nullable', Rule::enum(PhpVersion::class)],
            'package_manager' => ['nullable', Rule::enum(PackageManager::class)],
            'build_command' => ['nullable', 'string', 'max:500'],
            'source_control_id' => ['nullable', 'exists:source_controls,id'],
            'repository' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:100'],
            'install_composer' => ['nullable', 'boolean'],
            'create_database' => ['nullable', 'boolean'],
            'database_name' => [
                'nullable',
                'required_if:create_database,1',
                'string',
                'max:64',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique('databases', 'name')->where('server_id', $this->input('server_id')),
            ],
            'database_user' => [
                'nullable',
                'required_if:create_database,1',
                'string',
                'max:32',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique('database_users', 'username')->where('server_id', $this->input('server_id')),
            ],
            'database_password' => [
                'nullable',
                'required_if:create_database,1',
                'string',
                'min:8',
            ],
            'zero_downtime' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->siteValidationMessages(), [
            'server_id.required' => 'Please select a server.',
            'server_id.exists' => 'The selected server does not exist.',
            'database_id.exists' => 'The selected database does not exist on this server.',
            'database_user_id.exists' => 'The selected database user does not exist on this server.',
            'database_name.required_if' => 'Database name is required when creating a new database.',
            'database_name.regex' => 'Database name must start with a letter and contain only letters, numbers, and underscores.',
            'database_name.unique' => 'This database name already exists on the selected server.',
            'database_user.required_if' => 'Database user is required when creating a new database.',
            'database_user.regex' => 'Database user must start with a letter and contain only letters, numbers, and underscores.',
            'database_user.unique' => 'This database user already exists on the selected server.',
            'database_password.required_if' => 'Database password is required when creating a new database.',
            'database_password.min' => 'Database password must be at least 8 characters.',
        ]);
    }
}
