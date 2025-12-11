<?php

namespace Nip\Network\Providers;

use Nip\Support\Providers\NipServiceProvider;

class NetworkServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
