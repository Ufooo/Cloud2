<?php

namespace Nip\Site\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Models\Site;

class UpdateEnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Site $site */
        $site = $this->route('site');

        if ($site->status !== SiteStatus::Installed) {
            return false;
        }

        return Gate::allows('update', $site->server);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'environment' => ['nullable', 'string', 'max:65535'],
            'clear_config_cache' => ['sometimes', 'boolean'],
            'restart_queue' => ['sometimes', 'boolean'],
        ];
    }
}
