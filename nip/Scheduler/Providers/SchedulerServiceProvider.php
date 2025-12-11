<?php

namespace Nip\Scheduler\Providers;

use Nip\Support\Providers\NipServiceProvider;

class SchedulerServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
