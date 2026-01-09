<?php

namespace Nip\Server\Enums;

use App\Enums\Contracts\HasStatusBadge;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ServerStatus: string implements HasStatusBadge
{
    case Connecting = 'connecting';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Deleting = 'deleting';
    case Provisioning = 'provisioning';
    case Locked = 'locked';
    case Resizing = 'resizing';
    case Stopping = 'stopping';
    case Off = 'off';
    case Unknown = 'unknown';

    public function label(): string
    {
        return $this->name;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Connected => 'default',
            self::Disconnected, self::Deleting => 'destructive',
            self::Connecting, self::Provisioning, self::Resizing, self::Stopping, self::Locked, self::Off, self::Unknown => 'secondary',
        };
    }
}
