<?php

namespace Nip\Support\Providers;

use Illuminate\Support\ServiceProvider;

abstract class NipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $modulePath = $this->modulePath();

        if (file_exists($modulePath.'/Routes/web.php')) {
            $this->loadRoutesFrom($modulePath.'/Routes/web.php');
        }

        if (is_dir($modulePath.'/Database/Migrations')) {
            $this->loadMigrationsFrom($modulePath.'/Database/Migrations');
        }
    }

    abstract protected function modulePath(): string;
}
