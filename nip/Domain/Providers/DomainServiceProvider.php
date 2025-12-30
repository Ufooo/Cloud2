<?php

namespace Nip\Domain\Providers;

use Nip\Domain\Console\Commands\RenewExpiringCertificatesCommand;
use Nip\Support\Providers\NipServiceProvider;

class DomainServiceProvider extends NipServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                RenewExpiringCertificatesCommand::class,
            ]);
        }
    }

    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }
}
