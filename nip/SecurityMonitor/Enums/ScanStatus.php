<?php

namespace Nip\SecurityMonitor\Enums;

use App\Enums\Contracts\HasStatusBadge;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ScanStatus: string implements HasStatusBadge
{
    case Pending = 'pending';
    case Running = 'running';
    case Clean = 'clean';
    case IssuesDetected = 'issues_detected';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Clean => 'Clean',
            self::IssuesDetected => 'Issues Detected',
            self::Error => 'Error',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Running => 'info',
            self::Clean => 'success',
            self::IssuesDetected => 'warning',
            self::Error => 'destructive',
        };
    }
}
