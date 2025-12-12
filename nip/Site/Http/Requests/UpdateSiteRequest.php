<?php

namespace Nip\Site\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Server\Enums\IdentityColor;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteType;
use Nip\Site\Models\Site;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Site $site */
        $site = $this->route('site');

        return Gate::allows('update', $site->server);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Site $site */
        $site = $this->route('site');

        return [
            'domain' => [
                'sometimes',
                'string',
                'min:1',
                'max:255',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]$/',
                Rule::unique('sites')->where(function ($query) use ($site) {
                    return $query->where('server_id', $site->server_id);
                })->ignore($site->id),
            ],
            'type' => ['sometimes', Rule::enum(SiteType::class)],
            'user' => [
                'sometimes',
                'string',
                Rule::exists('unix_users', 'username')->where('server_id', $site->server_id),
            ],
            'root_directory' => ['nullable', 'string', 'max:255', 'regex:/^\//'],
            'web_directory' => ['nullable', 'string', 'max:255', 'regex:/^\//'],
            'php_version' => [
                'nullable',
                'string',
                Rule::exists('php_versions', 'version')
                    ->where('server_id', $site->server_id)
                    ->where('status', 'installed'),
            ],
            'package_manager' => ['nullable', Rule::enum(PackageManager::class)],
            'build_command' => ['nullable', 'string', 'max:500'],
            'repository' => ['nullable', 'string', 'max:255', 'regex:/^(git@|https:\/\/)/'],
            'branch' => ['nullable', 'string', 'max:100'],
            'is_isolated' => ['boolean'],
            'avatar_color' => ['nullable', Rule::enum(IdentityColor::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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
