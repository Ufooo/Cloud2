<?php

namespace Nip\Server\Enums;

use Nip\Server\Data\ServerProviderOptionData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ServerProvider: string
{
    case DigitalOcean = 'digitalocean';
    case Vultr = 'vultr';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::DigitalOcean => 'DigitalOcean',
            self::Vultr => 'Vultr',
            self::Custom => 'Custom VPS',
        };
    }

    /**
     * @return array<int, ServerProviderOptionData>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $provider) => new ServerProviderOptionData(
                value: $provider,
                label: $provider->label(),
            ),
            self::cases()
        );
    }
}
