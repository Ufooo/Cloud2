<?php

namespace Nip\Deployment\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DeploymentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::group([
            'middleware' => ['web', 'auth'],
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        });
    }
}
