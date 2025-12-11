<?php

namespace Nip\Php\Enums;

use App\Enums\Contracts\HasStatusBadge;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PhpVersionStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Installed = 'installed';
    case Uninstalling = 'uninstalling';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Installing => 'Installing',
            self::Installed => 'Installed',
            self::Uninstalling => 'Uninstalling',
            self::Failed => 'Failed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Installed => 'default',
            self::Installing, self::Pending => 'secondary',
            self::Failed => 'destructive',
            self::Uninstalling => 'outline',
        };
    }
}
