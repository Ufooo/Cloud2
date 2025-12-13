<?php

namespace Nip\Domain\Enums;

use App\Enums\Contracts\HasStatusBadge;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DomainRecordStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case Creating = 'creating';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case Updating = 'updating';
    case Securing = 'securing';
    case Removing = 'removing';
    case Disabling = 'disabling';
    case Enabling = 'enabling';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Creating => 'Creating',
            self::Enabled => 'Enabled',
            self::Disabled => 'Disabled',
            self::Updating => 'Updating',
            self::Securing => 'Securing',
            self::Removing => 'Removing',
            self::Disabling => 'Disabling',
            self::Enabling => 'Enabling',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Enabled => 'default',
            self::Disabled => 'secondary',
            self::Pending, self::Creating, self::Updating, self::Securing, self::Disabling, self::Enabling => 'outline',
            self::Removing => 'destructive',
        };
    }
}
