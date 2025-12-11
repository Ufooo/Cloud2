<?php

namespace Nip\Network\Enums;

use App\Enums\Concerns\HasOptions;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RuleType: string
{
    use HasOptions;

    case Allow = 'allow';
    case Deny = 'deny';

    public function label(): string
    {
        return match ($this) {
            self::Allow => 'Allow',
            self::Deny => 'Deny',
        };
    }
}
