<?php

namespace Nip\Scheduler\Enums;

use App\Enums\Concerns\HasOptions;

enum CronFrequency: string
{
    use HasOptions;

    case EveryMinute = 'every_minute';
    case Hourly = 'hourly';
    case Nightly = 'nightly';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case OnReboot = 'on_reboot';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::EveryMinute => 'Every minute',
            self::Hourly => 'Hourly',
            self::Nightly => 'Nightly',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::OnReboot => 'On reboot',
            self::Custom => 'Custom frequency',
        };
    }

    public function cronExpression(): ?string
    {
        return match ($this) {
            self::EveryMinute => '* * * * *',
            self::Hourly => '0 * * * *',
            self::Nightly => '0 0 * * *',
            self::Weekly => '0 0 * * 0',
            self::Monthly => '0 0 1 * *',
            self::OnReboot => '@reboot',
            self::Custom => null,
        };
    }
}
