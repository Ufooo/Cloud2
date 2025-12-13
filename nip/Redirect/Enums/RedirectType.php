<?php

namespace Nip\Redirect\Enums;

enum RedirectType: string
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent (301)',
            self::Temporary => 'Temporary (302)',
        };
    }

    public function httpCode(): int
    {
        return match ($this) {
            self::Permanent => 301,
            self::Temporary => 302,
        };
    }
}
