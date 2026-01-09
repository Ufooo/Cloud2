<?php

namespace Nip\Site\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Php\Enums\PhpVersion;
use Nip\Server\Enums\IdentityColor;
use Nip\Shared\Traits\HasSiteValidationMessages;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteType;
use Nip\Site\Models\Site;

class UpdateSiteRequest extends FormRequest
{
    use HasSiteValidationMessages;

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
            'php_version' => ['nullable', Rule::enum(PhpVersion::class)],
            'package_manager' => ['nullable', Rule::enum(PackageManager::class)],
            'build_command' => ['nullable', 'string', 'max:500'],
            'repository' => ['nullable', 'string', 'max:255', 'regex:/^(git@|https:\/\/)/'],
            'branch' => ['nullable', 'string', 'max:100'],
            'avatar_color' => ['nullable', Rule::enum(IdentityColor::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->siteValidationMessages();
    }
}
