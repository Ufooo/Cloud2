<?php

namespace Nip\Scheduler\Enums;

use App\Enums\Concerns\HasOptions;

enum GracePeriod: int
{
    use HasOptions;

    case OneMinute = 1;
    case TwoMinutes = 2;
    case FiveMinutes = 5;
    case TenMinutes = 10;
    case ThirtyMinutes = 30;
    case OneHour = 60;

    public function label(): string
    {
        return match ($this) {
            self::OneMinute => 'After 1 minute',
            self::TwoMinutes => 'After 2 minutes',
            self::FiveMinutes => 'After 5 minutes',
            self::TenMinutes => 'After 10 minutes',
            self::ThirtyMinutes => 'After 30 minutes',
            self::OneHour => 'After 1 hour',
        };
    }
}
