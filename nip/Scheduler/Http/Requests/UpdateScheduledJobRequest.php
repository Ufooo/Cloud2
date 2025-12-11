<?php

namespace Nip\Scheduler\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\Scheduler\Enums\CronFrequency;
use Nip\Scheduler\Enums\GracePeriod;
use Nip\Server\Models\Server;

class UpdateScheduledJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('server'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Server $server */
        $server = $this->route('server');

        $users = $server->unixUsers()->pluck('username')->toArray();

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
