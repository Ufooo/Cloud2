<?php

namespace Nip\Scheduler\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Scheduler\Enums\CronFrequency;
use Nip\Scheduler\Enums\GracePeriod;
use Nip\Site\Models\Site;

class UpdateSiteScheduledJobRequest extends FormRequest
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

        $users = $site->server->unixUsers()->pluck('username')->toArray();

        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:255'],
            'command' => ['sometimes', 'string', 'min:1', 'max:1000'],
            'user' => ['sometimes', 'string', Rule::in($users)],
            'frequency' => ['sometimes', Rule::enum(CronFrequency::class)],
            'cron' => [
                'nullable',
                'string',
                'max:100',
                'required_if:frequency,custom',
                'regex:/^(\*|[0-9,\-\/]+)\s+(\*|[0-9,\-\/]+)\s+(\*|[0-9,\-\/]+)\s+(\*|[0-9,\-\/]+)\s+(\*|[0-9,\-\/]+)$/',
            ],
            'heartbeat_enabled' => ['boolean'],
            'grace_period' => ['nullable', Rule::enum(GracePeriod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user.in' => 'The selected user does not exist on this server.',
            'cron.required_if' => 'A cron expression is required when using custom frequency.',
            'cron.regex' => 'The cron expression format is invalid.',
        ];
    }
}
