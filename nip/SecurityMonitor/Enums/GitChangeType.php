<?php

namespace Nip\SecurityMonitor\Enums;

use App\Enums\Contracts\HasStatusBadge;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum GitChangeType: string implements HasStatusBadge
{
    case Modified = 'modified';
    case Untracked = 'untracked';
    case Deleted = 'deleted';
    case Added = 'added';
    case Renamed = 'renamed';
    case Copied = 'copied';
    case Unknown = 'unknown';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::Modified => 'Modified',
            self::Untracked => 'Untracked',
            self::Deleted => 'Deleted',
            self::Added => 'Added',
            self::Renamed => 'Renamed',
            self::Copied => 'Copied',
            self::Unknown => 'Unknown',
            self::Any => 'Any',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Modified => 'info',
            self::Untracked => 'warning',
            self::Deleted => 'destructive',
            self::Added => 'success',
            self::Renamed => 'secondary',
            self::Copied => 'secondary',
            self::Unknown => 'outline',
            self::Any => 'outline',
        };
    }
}
