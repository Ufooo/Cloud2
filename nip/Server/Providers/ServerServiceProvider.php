<?php

namespace Nip\Server\Providers;

use Illuminate\Support\Facades\Gate;
use Nip\Server\Console\Commands\CollectServerMetricsCommand;
use Nip\Server\Models\Server;
use Nip\Server\Policies\ServerPolicy;
use Nip\Support\Providers\NipServiceProvider;

class ServerServiceProvider extends NipServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__.'/..';
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Server::class, ServerPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CollectServerMetricsCommand::class,
            ]);
        }
    }
}
