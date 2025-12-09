<?php

namespace Nip\Server\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum UbuntuVersion: string
{
    case V2404 = '24.04';
    case V2204 = '22.04';
    case V2004 = '20.04';

    public function label(): string
    {
        return match ($this) {
            self::V2404 => 'Ubuntu 24.04 LTS',
            self::V2204 => 'Ubuntu 22.04 LTS',
            self::V2004 => 'Ubuntu 20.04 LTS',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $version) => [
                'value' => $version->value,
                'label' => $version->label(),
            ],
            self::cases()
        );
    }
}
