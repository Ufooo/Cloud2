<?php

namespace Nip\Php\Providers;

use Nip\Support\Providers\NipServiceProvider;

class PhpServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
