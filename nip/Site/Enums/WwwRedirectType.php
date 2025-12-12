<?php

namespace Nip\Site\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum WwwRedirectType: string
{
    case FromWww = 'from_www';
    case ToWww = 'to_www';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::FromWww => 'Redirect from www',
            self::ToWww => 'Redirect to www',
            self::None => 'No redirect',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FromWww => 'www.example.com → example.com',
            self::ToWww => 'example.com → www.example.com',
            self::None => 'No www redirect will be applied',
        };
    }

    /**
     * @return array<array{value: string, label: string, description: string, isDefault: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'isDefault' => $type === self::FromWww,
            ],
            self::cases()
        );
    }
}
