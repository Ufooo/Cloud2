<?php

namespace Nip\Server\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nip\Server\Enums\DatabaseType;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\PhpVersion;
use Nip\Server\Enums\ServerProvider;
use Nip\Server\Enums\ServerType;
use Nip\Server\Enums\UbuntuVersion;

class StoreServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::enum(ServerProvider::class)],
            'type' => ['required', 'string', Rule::enum(ServerType::class)],
            'ip_address' => ['required', 'ip'],
            'private_ip_address' => ['nullable', 'ip'],
            'ssh_port' => ['required', 'string', 'max:10'],
            'php_version' => ['required_if:type,app,web,worker', 'nullable', 'string', Rule::enum(PhpVersion::class)],
            'database_type' => ['nullable', 'string', Rule::enum(DatabaseType::class)],
            'ubuntu_version' => ['required', 'string', Rule::enum(UbuntuVersion::class)],
            'timezone' => ['required', 'string', 'timezone'],
            'notes' => ['nullable', 'string'],
            'avatar_color' => ['nullable', 'string', Rule::enum(IdentityColor::class)],
            'services' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Server name is required.',
            'provider.required' => 'Provider is required.',
            'provider.in' => 'Invalid provider selected.',
            'type.required' => 'Server type is required.',
            'type.in' => 'Invalid server type selected.',
            'ip_address.required' => 'IP address is required.',
            'ip_address.ip' => 'Invalid IP address format.',
            'private_ip_address.ip' => 'Invalid private IP address format.',
            'ssh_port.required' => 'SSH port is required.',
            'timezone.required' => 'Timezone is required.',
            'timezone.timezone' => 'Invalid timezone.',
        ];
    }
}
