<?php

namespace Nip\Redirect\Enums;

use App\Enums\Contracts\HasStatusBadge;

enum RedirectRuleStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Installed = 'installed';
    case Updating = 'updating';
    case Removing = 'removing';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Installing => 'Installing',
            self::Installed => 'Installed',
            self::Updating => 'Updating',
            self::Removing => 'Removing',
            self::Failed => 'Failed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Installed => 'default',
            self::Installing, self::Pending, self::Updating, self::Removing => 'secondary',
            self::Failed => 'destructive',
        };
    }
}
