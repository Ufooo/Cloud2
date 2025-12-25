<?php

namespace Nip\Database\Enums;

use App\Enums\Contracts\HasStatusBadge;

enum DatabaseUserStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Syncing = 'syncing';
    case Installed = 'installed';
    case Deleting = 'deleting';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Installing => 'Installing',
            self::Syncing => 'Syncing',
            self::Installed => 'Installed',
            self::Deleting => 'Deleting',
            self::Failed => 'Failed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Installed => 'default',
            self::Installing, self::Syncing, self::Pending => 'secondary',
            self::Deleting => 'outline',
            self::Failed => 'destructive',
        };
    }
}
