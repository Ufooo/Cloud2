<?php

namespace Nip\Process\Providers;

use Nip\Support\Providers\NipServiceProvider;

class ProcessServiceProvider extends NipServiceProvider
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
