<?php

namespace Nip\Server\Enums;

enum ProvisionScriptStatus: string
{
    case Pending = 'pending';
    case Executing = 'executing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Executing => 'Executing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
