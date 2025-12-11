<?php

namespace Nip\Scheduler\Providers;

use Nip\Support\Providers\NipServiceProvider;

class SchedulerServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }

    public function boot(): void
    {
        $this->loadRoutesFrom($this->modulePath().'/Routes/web.php');
    }
}
