<?php

namespace Nip\Network\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RuleStatus: string
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Installed = 'installed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Installing => 'Installing',
            self::Installed => 'Installed',
            self::Failed => 'Failed',
        };
    }
}
