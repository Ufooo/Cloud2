<?php

namespace Nip\BackgroundProcess\Enums;

use App\Enums\Contracts\HasStatusBadge;

enum SupervisorProcessStatus: string implements HasStatusBadge
{
    case Running = 'RUNNING';
    case Starting = 'STARTING';
    case Stopping = 'STOPPING';
    case Stopped = 'STOPPED';
    case Backoff = 'BACKOFF';
    case Exited = 'EXITED';
    case Fatal = 'FATAL';
    case Unknown = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Starting => 'Starting',
            self::Stopping => 'Stopping',
            self::Stopped => 'Stopped',
            self::Backoff => 'Backoff',
            self::Exited => 'Exited',
            self::Fatal => 'Fatal',
            self::Unknown => 'Unknown',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Running => 'default',
            self::Starting, self::Backoff => 'secondary',
            self::Stopping, self::Stopped, self::Exited => 'outline',
            self::Fatal, self::Unknown => 'destructive',
        };
    }
}
