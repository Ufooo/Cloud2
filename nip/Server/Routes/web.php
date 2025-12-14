<?php

use Illuminate\Support\Facades\Route;
use Nip\Server\Http\Controllers\ProvisioningController;
use Nip\Server\Http\Controllers\ServerController;

// Public provisioning routes (no auth required, token-based)
Route::middleware(['web'])->group(function () {
    Route::get('/servers/{server:id}/provision', [ProvisioningController::class, 'script'])
        ->name('provisioning.script');
    Route::post('/provisioning/callback/status', [ProvisioningController::class, 'callback'])
        ->name('provisioning.callback');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
    Route::get('/servers/create', [ServerController::class, 'create'])->name('servers.create');
    Route::post('/servers', [ServerController::class, 'store'])->name('servers.store');

    Route::prefix('/servers/{server:slug}')->group(function () {
        Route::get('/', [ServerController::class, 'show'])->name('servers.show');
        Route::delete('/', [ServerController::class, 'destroy'])->name('servers.destroy');

        Route::middleware('server.connected')->group(function () {
            Route::get('/settings', [ServerController::class, 'settings'])->name('servers.settings');
            Route::patch('/settings', [ServerController::class, 'update'])->name('servers.update');
        });
    });
});
