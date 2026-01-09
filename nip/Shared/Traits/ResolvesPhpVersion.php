<?php

namespace Nip\Shared\Traits;

use Nip\Php\Enums\PhpVersion;

trait ResolvesPhpVersion
{
    protected function resolvePhpVersionString(string $phpVersion): string
    {
        return PhpVersion::tryFrom($phpVersion)?->version() ?? $phpVersion;
    }
}
