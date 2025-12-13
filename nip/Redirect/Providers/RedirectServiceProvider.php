<?php

namespace Nip\Redirect\Providers;

use Illuminate\Support\ServiceProvider;

class RedirectServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
