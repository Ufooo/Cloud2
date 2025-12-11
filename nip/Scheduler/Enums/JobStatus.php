<?php

namespace Nip\Scheduler\Enums;

use App\Enums\Contracts\HasStatusBadge;

enum JobStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Installed = 'installed';
    case Paused = 'paused';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Installing => 'Installing',
            self::Installed => 'Installed',
            self::Paused => 'Paused',
            self::Failed => 'Failed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Installed => 'default',
            self::Installing, self::Pending => 'secondary',
            self::Paused => 'outline',
            self::Failed => 'destructive',
        };
    }
}
