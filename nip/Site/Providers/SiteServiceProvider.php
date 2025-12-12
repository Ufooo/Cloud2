<?php

namespace Nip\Site\Providers;

use Nip\Support\Providers\NipServiceProvider;

class SiteServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
