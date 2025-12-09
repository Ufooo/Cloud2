<?php

namespace Nip\Server\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Nip\Server\Models\Server;
use Nip\Server\Policies\ServerPolicy;

class ServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        Gate::policy(Server::class, ServerPolicy::class);
    }
}
