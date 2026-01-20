<?php

namespace Nip\SecurityMonitor\Providers;

use Nip\SecurityMonitor\Console\Commands\SecurityCleanupCommand;
use Nip\SecurityMonitor\Console\Commands\SecurityScanCommand;
use Nip\SecurityMonitor\Console\Commands\SecurityStatusCommand;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\SecurityMonitor\Observers\SecurityScanObserver;
use Nip\Support\Providers\NipServiceProvider;

class SecurityMonitorServiceProvider extends NipServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        SecurityScan::observe(SecurityScanObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SecurityScanCommand::class,
                SecurityCleanupCommand::class,
                SecurityStatusCommand::class,
            ]);
        }
    }

    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
