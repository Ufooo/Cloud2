<?php

namespace Nip\Server\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum Timezone: string
{
    case UTC = 'UTC';
    case EuropeLondon = 'Europe/London';
    case EuropeParis = 'Europe/Paris';
    case EuropeBerlin = 'Europe/Berlin';
    case EuropeBudapest = 'Europe/Budapest';
    case EuropeMoscow = 'Europe/Moscow';
    case AmericaNewYork = 'America/New_York';
    case AmericaChicago = 'America/Chicago';
    case AmericaDenver = 'America/Denver';
    case AmericaLosAngeles = 'America/Los_Angeles';
    case AsiaTokyo = 'Asia/Tokyo';
    case AsiaShanghai = 'Asia/Shanghai';
    case AsiaSingapore = 'Asia/Singapore';
    case AustraliaSydney = 'Australia/Sydney';

    public function label(): string
    {
        return match ($this) {
            self::UTC => 'UTC',
            self::EuropeLondon => 'Europe/London',
            self::EuropeParis => 'Europe/Paris',
            self::EuropeBerlin => 'Europe/Berlin',
            self::EuropeBudapest => 'Europe/Budapest',
            self::EuropeMoscow => 'Europe/Moscow',
            self::AmericaNewYork => 'America/New York',
            self::AmericaChicago => 'America/Chicago',
            self::AmericaDenver => 'America/Denver',
            self::AmericaLosAngeles => 'America/Los Angeles',
            self::AsiaTokyo => 'Asia/Tokyo',
            self::AsiaShanghai => 'Asia/Shanghai',
            self::AsiaSingapore => 'Asia/Singapore',
            self::AustraliaSydney => 'Australia/Sydney',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $timezone) => [
                'value' => $timezone->value,
                'label' => $timezone->label(),
            ],
            self::cases()
        );
    }
}
