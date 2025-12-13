<?php

namespace Nip\Domain\Providers;

use Nip\Support\Providers\NipServiceProvider;

class DomainServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
