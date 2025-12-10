<?php

namespace Nip\Server\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum IdentityColor: string
{
    case Blue = 'blue';
    case Green = 'green';
    case Orange = 'orange';
    case Purple = 'purple';
    case Red = 'red';
    case Yellow = 'yellow';
    case Cyan = 'cyan';
    case Gray = 'gray';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $color) => [
                'value' => $color->value,
                'label' => ucfirst($color->value),
            ],
            self::cases()
        );
    }
}
