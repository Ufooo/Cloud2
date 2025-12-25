<?php

namespace Nip\UnixUser\Enums;

use App\Enums\Contracts\HasStatusBadge;

enum UserStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Installed = 'installed';
    case Deleting = 'deleting';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Installing => 'Installing',
            self::Installed => 'Installed',
            self::Deleting => 'Deleting',
            self::Failed => 'Failed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Installed => 'default',
            self::Installing, self::Pending, self::Deleting => 'secondary',
            self::Failed => 'destructive',
        };
    }
}
