<?php

namespace Nip\Site\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Server\Models\Server;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteType;
use Nip\Site\Enums\WwwRedirectType;

class StoreSiteRequest extends FormRequest
{
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
            'php_version' => [
                'nullable',
                'string',
                Rule::exists('php_versions', 'version')
                    ->where('server_id', $this->input('server_id'))
                    ->where('status', 'installed'),
            ],
            'package_manager' => ['nullable', Rule::enum(PackageManager::class)],
            'build_command' => ['nullable', 'string', 'max:500'],
            'source_control_id' => ['nullable', 'exists:source_controls,id'],
            'repository' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:100'],
            'install_composer' => ['nullable', 'boolean'],
            'create_database' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'server_id.required' => 'Please select a server.',
            'server_id.exists' => 'The selected server does not exist.',
            'database_id.exists' => 'The selected database does not exist on this server.',
            'database_user_id.exists' => 'The selected database user does not exist on this server.',
            'domain.regex' => 'The domain format is invalid.',
            'domain.unique' => 'This domain is already configured on this server.',
            'user.exists' => 'The selected user does not exist on this server.',
            'php_version.exists' => 'The selected PHP version is not installed on this server.',
            'root_directory.regex' => 'The root directory must start with /.',
            'web_directory.regex' => 'The web directory must start with /.',
            'repository.regex' => 'The repository must be a valid git URL (git@ or https://).',
        ];
    }
}
