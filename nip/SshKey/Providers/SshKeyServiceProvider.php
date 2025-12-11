<?php

namespace Nip\SshKey\Providers;

use Nip\Support\Providers\NipServiceProvider;

class SshKeyServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
