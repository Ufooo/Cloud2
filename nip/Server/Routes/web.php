<?php

use Illuminate\Support\Facades\Route;
use Nip\Server\Http\Controllers\ServerController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
    Route::get('/servers/create', [ServerController::class, 'create'])->name('servers.create');
    Route::post('/servers', [ServerController::class, 'store'])->name('servers.store');

    Route::prefix('/servers/{server:slug}')->group(function () {
        Route::get('/', [ServerController::class, 'show'])->name('servers.show');
        Route::delete('/', [ServerController::class, 'destroy'])->name('servers.destroy');
    });
});
