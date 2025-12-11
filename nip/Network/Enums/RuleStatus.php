<?php

namespace Nip\Network\Enums;

use App\Enums\Contracts\HasStatusBadge;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RuleStatus: string implements HasStatusBadge
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

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Installed => 'default',
            self::Installing, self::Pending => 'secondary',
            self::Failed => 'destructive',
        };
    }
}
