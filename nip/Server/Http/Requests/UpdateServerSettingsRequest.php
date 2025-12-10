<?php

namespace Nip\Server\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\Timezone;

class UpdateServerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('server'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ssh_port' => ['required', 'string', 'max:5'],
            'ip_address' => ['nullable', 'string', 'ip'],
            'private_ip_address' => ['nullable', 'string', 'ip'],
            'timezone' => ['required', Rule::enum(Timezone::class)],
            'avatar_color' => ['required', Rule::enum(IdentityColor::class)],
        ];
    }
}
