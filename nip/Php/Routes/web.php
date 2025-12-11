<?php

use Illuminate\Support\Facades\Route;
use Nip\Php\Http\Controllers\PhpController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('/servers/{server:slug}/php')->group(function () {
        Route::get('/', [PhpController::class, 'index'])
            ->name('servers.php');

        Route::patch('/settings', [PhpController::class, 'updateSettings'])
            ->name('servers.php.settings.update');

        Route::post('/versions', [PhpController::class, 'installVersion'])
            ->name('servers.php.versions.install');

        Route::delete('/versions/{phpVersion}', [PhpController::class, 'uninstallVersion'])
            ->name('servers.php.versions.uninstall');

        Route::post('/versions/{phpVersion}/default', [PhpController::class, 'setDefault'])
            ->name('servers.php.versions.setDefault');
    });
});
