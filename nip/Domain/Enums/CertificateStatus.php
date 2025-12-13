<?php

namespace Nip\Domain\Enums;

use App\Enums\Contracts\HasStatusBadge;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum CertificateStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case PendingVerification = 'pending_verification';
    case Installing = 'installing';
    case Installed = 'installed';
    case Renewing = 'renewing';
    case Removing = 'removing';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PendingVerification => 'Pending Verification',
            self::Installing => 'Installing',
            self::Installed => 'Installed',
            self::Renewing => 'Renewing',
            self::Removing => 'Removing',
            self::Failed => 'Failed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Installed => 'default',
            self::Pending, self::PendingVerification, self::Installing, self::Renewing => 'secondary',
            self::Removing => 'outline',
            self::Failed => 'destructive',
        };
    }
}
