<?php

namespace Nip\Server\Enums;

use App\Enums\Concerns\HasOptions;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PhpVersion: string
{
    use HasOptions;

    case Php84 = 'php84';
    case Php83 = 'php83';
    case Php82 = 'php82';
    case Php81 = 'php81';

    public function label(): string
    {
        return match ($this) {
            self::Php84 => 'PHP 8.4',
            self::Php83 => 'PHP 8.3',
            self::Php82 => 'PHP 8.2',
            self::Php81 => 'PHP 8.1',
        };
    }
}
