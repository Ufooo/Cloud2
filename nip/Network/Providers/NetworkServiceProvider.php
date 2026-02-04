<?php

namespace Nip\Network\Providers;

use Nip\Network\Console\Commands\SyncOpenVpnClientsCommand;
use Nip\Support\Providers\NipServiceProvider;

class NetworkServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncOpenVpnClientsCommand::class,
            ]);
        }
    }
}
