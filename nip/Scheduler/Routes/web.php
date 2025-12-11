<?php

use Illuminate\Support\Facades\Route;
use Nip\Scheduler\Http\Controllers\ScheduledJobController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::middleware('server.connected')->group(function () {
        Route::prefix('/servers/{server:slug}/scheduler')->group(function () {
            Route::get('/', [ScheduledJobController::class, 'index'])->name('servers.scheduler');
            Route::post('/', [ScheduledJobController::class, 'store'])->name('servers.scheduler.store');
            Route::patch('/{job}', [ScheduledJobController::class, 'update'])->name('servers.scheduler.update');
            Route::delete('/{job}', [ScheduledJobController::class, 'destroy'])->name('servers.scheduler.destroy');
            Route::post('/{job}/pause', [ScheduledJobController::class, 'pause'])->name('servers.scheduler.pause');
            Route::post('/{job}/resume', [ScheduledJobController::class, 'resume'])->name('servers.scheduler.resume');
        });
    });
});
