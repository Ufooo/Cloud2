<?php

namespace Nip\BackgroundProcess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\BackgroundProcess\Enums\StopSignal;
use Nip\Site\Models\Site;

class StoreSiteBackgroundProcessRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'command' => ['required', 'string', 'min:1', 'max:1000'],
            'directory' => ['nullable', 'string', 'max:255'],
            'user' => ['required', 'string', Rule::in($users)],
            'processes' => ['required', 'integer', 'min:1', 'max:100'],
            'startsecs' => ['required', 'integer', 'min:0', 'max:3600'],
            'stopwaitsecs' => ['required', 'integer', 'min:0', 'max:3600'],
            'stopsignal' => ['required', Rule::enum(StopSignal::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user.in' => 'The selected user does not exist on this server.',
            'processes.max' => 'You can run a maximum of 100 processes.',
        ];
    }
}
