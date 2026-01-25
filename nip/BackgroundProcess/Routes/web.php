<?php

use Illuminate\Support\Facades\Route;
use Nip\BackgroundProcess\Http\Controllers\BackgroundProcessController;
use Nip\BackgroundProcess\Http\Controllers\SiteBackgroundProcessController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::middleware('server.connected')->group(function () {
        Route::prefix('/servers/{server:slug}/background-processes')->group(function () {
            Route::get('/', [BackgroundProcessController::class, 'index'])->name('servers.background-processes');
            Route::post('/', [BackgroundProcessController::class, 'store'])->name('servers.background-processes.store');
            Route::patch('/{process}', [BackgroundProcessController::class, 'update'])->name('servers.background-processes.update');
            Route::delete('/{process}', [BackgroundProcessController::class, 'destroy'])->name('servers.background-processes.destroy');
            Route::post('/{process}/restart', [BackgroundProcessController::class, 'restart'])->name('servers.background-processes.restart');
            Route::post('/{process}/start', [BackgroundProcessController::class, 'start'])->name('servers.background-processes.start');
            Route::post('/{process}/stop', [BackgroundProcessController::class, 'stop'])->name('servers.background-processes.stop');
        });

        Route::prefix('/sites/{site:slug}/background-processes')->group(function () {
            Route::get('/', [SiteBackgroundProcessController::class, 'index'])->name('sites.background-processes');
            Route::post('/', [SiteBackgroundProcessController::class, 'store'])->name('sites.background-processes.store');
            Route::patch('/{process}', [SiteBackgroundProcessController::class, 'update'])->name('sites.background-processes.update');
            Route::delete('/{process}', [SiteBackgroundProcessController::class, 'destroy'])->name('sites.background-processes.destroy');
            Route::post('/{process}/restart', [SiteBackgroundProcessController::class, 'restart'])->name('sites.background-processes.restart');
            Route::post('/{process}/start', [SiteBackgroundProcessController::class, 'start'])->name('sites.background-processes.start');
            Route::post('/{process}/stop', [SiteBackgroundProcessController::class, 'stop'])->name('sites.background-processes.stop');
            Route::get('/{process}/logs', [SiteBackgroundProcessController::class, 'viewLogs'])->name('sites.background-processes.logs');
            Route::delete('/{process}/logs', [SiteBackgroundProcessController::class, 'clearLogs'])->name('sites.background-processes.logs.clear');
            Route::get('/{process}/status', [SiteBackgroundProcessController::class, 'viewStatus'])->name('sites.background-processes.status');
        });
    });
});
