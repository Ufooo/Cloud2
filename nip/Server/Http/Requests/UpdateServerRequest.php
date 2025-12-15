<?php

namespace Nip\Server\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nip\Php\Enums\PhpVersion;
use Nip\Server\Enums\DatabaseType;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\ServerProvider;
use Nip\Server\Enums\ServerType;
use Nip\Server\Enums\UbuntuVersion;

class UpdateServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('server'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'provider' => ['sometimes', 'required', 'string', Rule::enum(ServerProvider::class)],
            'type' => ['sometimes', 'required', 'string', Rule::enum(ServerType::class)],
            'ip_address' => ['nullable', 'ip'],
            'private_ip_address' => ['nullable', 'ip'],
            'ssh_port' => ['nullable', 'string', 'max:10'],
            'php_version' => ['nullable', 'string', Rule::enum(PhpVersion::class)],
            'database_type' => ['nullable', 'string', Rule::enum(DatabaseType::class)],
            'ubuntu_version' => ['nullable', 'string', Rule::enum(UbuntuVersion::class)],
            'timezone' => ['nullable', 'string', 'timezone'],
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
            'ip_address.ip' => 'Invalid IP address format.',
            'private_ip_address.ip' => 'Invalid private IP address format.',
            'timezone.timezone' => 'Invalid timezone.',
        ];
    }
}
