<?php

namespace Nip\SecurityMonitor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\Site\Models\Site;

class CreateGitWhitelistRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Let validation handle missing/invalid site_id
        $siteId = $this->input('site_id');
        if (! $siteId) {
            return true;
        }

        $site = Site::find($siteId);
        if (! $site) {
            return true; // Let validation handle non-existent site
        }

        return Gate::allows('update', $site->server);
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'exists:sites,id'],
            'file_path' => ['required', 'string', 'max:1000'],
            'change_type' => ['required', Rule::enum(GitChangeType::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
