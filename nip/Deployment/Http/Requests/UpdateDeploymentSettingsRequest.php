<?php

namespace Nip\Deployment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeploymentSettingsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'deploy_script' => ['nullable', 'string', 'max:65535'],
            'push_to_deploy' => ['boolean'],
            'auto_source' => ['boolean'],
            'deployment_retention' => ['integer', 'min:1', 'max:100'],
            'healthcheck_endpoint' => ['nullable', 'url', 'max:255'],
        ];
    }
}
