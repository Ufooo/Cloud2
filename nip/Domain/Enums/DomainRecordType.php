<?php

namespace Nip\Domain\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DomainRecordType: string
{
    case Primary = 'primary';
    case Alias = 'alias';
    case Reverb = 'reverb';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary',
            self::Alias => 'Alias',
            self::Reverb => 'Reverb',
        };
    }
}
