<?php

namespace Nip\SecurityMonitor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Site\Models\Site;

class UpdateSecuritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        if (! $site instanceof Site) {
            return true;
        }

        return Gate::allows('update', $site->server);
    }

    public function rules(): array
    {
        return [
            'security_scan_interval_minutes' => ['sometimes', 'integer', Rule::in([15, 30, 60, 120, 360, 720, 1440])],
            'security_scan_retention_days' => ['sometimes', 'integer', Rule::in([1, 3, 7, 14, 30])],
            'git_monitor_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
