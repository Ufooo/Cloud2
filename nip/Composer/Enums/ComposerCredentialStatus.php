<?php

namespace Nip\Composer\Enums;

use App\Enums\Contracts\HasStatusBadge;

enum ComposerCredentialStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Synced = 'synced';
    case Deleting = 'deleting';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Syncing => 'Syncing',
            self::Synced => 'Synced',
            self::Deleting => 'Deleting',
            self::Failed => 'Failed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Synced => 'default',
            self::Syncing, self::Pending => 'secondary',
            self::Deleting => 'outline',
            self::Failed => 'destructive',
        };
    }
}
