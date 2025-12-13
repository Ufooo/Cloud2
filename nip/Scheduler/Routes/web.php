<?php

use Illuminate\Support\Facades\Route;
use Nip\Scheduler\Http\Controllers\ScheduledJobController;
use Nip\Scheduler\Http\Controllers\SiteScheduledJobController;

Route::middleware(['web', 'auth'])->group(function () {
    // Server scheduler routes
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

    // Site scheduler routes
    Route::prefix('/sites/{site:slug}/scheduler')->group(function () {
        Route::get('/', [SiteScheduledJobController::class, 'index'])->name('sites.scheduler');
        Route::post('/', [SiteScheduledJobController::class, 'store'])->name('sites.scheduler.store');
        Route::patch('/{job}', [SiteScheduledJobController::class, 'update'])->name('sites.scheduler.update');
        Route::delete('/{job}', [SiteScheduledJobController::class, 'destroy'])->name('sites.scheduler.destroy');
        Route::post('/{job}/pause', [SiteScheduledJobController::class, 'pause'])->name('sites.scheduler.pause');
        Route::post('/{job}/resume', [SiteScheduledJobController::class, 'resume'])->name('sites.scheduler.resume');
    });
});
