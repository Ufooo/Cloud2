<?php

namespace Nip\UnixUser\Providers;

use Nip\Support\Providers\NipServiceProvider;

class UnixUserServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
