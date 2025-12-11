<?php

namespace Nip\BackgroundProcess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Nip\BackgroundProcess\Enums\StopSignal;
use Nip\Server\Models\Server;

class UpdateBackgroundProcessRequest extends FormRequest
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
            'directory' => ['nullable', 'string', 'max:255'],
            'user' => ['sometimes', 'string', Rule::in($users)],
            'processes' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'startsecs' => ['sometimes', 'integer', 'min:0', 'max:3600'],
            'stopwaitsecs' => ['sometimes', 'integer', 'min:0', 'max:3600'],
            'stopsignal' => ['sometimes', Rule::enum(StopSignal::class)],
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
