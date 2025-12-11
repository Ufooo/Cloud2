<?php

namespace Nip\BackgroundProcess\Enums;

use App\Enums\Concerns\HasOptions;

enum StopSignal: string
{
    use HasOptions;

    case TERM = 'TERM';
    case HUP = 'HUP';
    case INT = 'INT';
    case QUIT = 'QUIT';
    case KILL = 'KILL';
    case USR1 = 'USR1';
    case USR2 = 'USR2';

    public function label(): string
    {
        return match ($this) {
            self::TERM => 'SIGTERM (Graceful shutdown)',
            self::HUP => 'SIGHUP (Reload configuration)',
            self::INT => 'SIGINT (Interrupt)',
            self::QUIT => 'SIGQUIT (Quit)',
            self::KILL => 'SIGKILL (Force kill)',
            self::USR1 => 'SIGUSR1 (User defined 1)',
            self::USR2 => 'SIGUSR2 (User defined 2)',
        };
    }
}
