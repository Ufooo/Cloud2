<?php

namespace Nip\BackgroundProcess\Providers;

use Nip\Support\Providers\NipServiceProvider;

class BackgroundProcessServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
