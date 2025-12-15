<?php

namespace Nip\SshKey\Enums;

use App\Enums\Contracts\HasStatusBadge;

enum SshKeyStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case Installed = 'installed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Installed => 'Installed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Installed => 'default',
            self::Pending => 'secondary',
        };
    }
}
