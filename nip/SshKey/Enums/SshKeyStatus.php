<?php

namespace Nip\SshKey\Enums;

use App\Enums\Contracts\HasStatusBadge;

enum SshKeyStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case Installed = 'installed';
    case Failed = 'failed';
    case Deleting = 'deleting';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Installing...',
            self::Installed => 'Installed',
            self::Failed => 'Failed',
            self::Deleting => 'Deleting...',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Installed => 'default',
            self::Pending, self::Deleting => 'secondary',
            self::Failed => 'destructive',
        };
    }
}
