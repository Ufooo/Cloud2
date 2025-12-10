<?php

namespace Nip\Network\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RuleType: string
{
    case Allow = 'allow';
    case Deny = 'deny';

    public function label(): string
    {
        return match ($this) {
            self::Allow => 'Allow',
            self::Deny => 'Deny',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases()
        );
    }
}
