<?php

use Illuminate\Support\Facades\Route;
use Nip\BackgroundProcess\Http\Controllers\BackgroundProcessController;

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
    });
});
