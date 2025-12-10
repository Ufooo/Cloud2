<?php

use Illuminate\Support\Facades\Route;
use Nip\Network\Http\Controllers\NetworkController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('/servers/{server:slug}/network')->group(function () {
        Route::get('/', [NetworkController::class, 'index'])->name('servers.network');
        Route::post('/rules', [NetworkController::class, 'store'])->name('servers.network.rules.store');
        Route::delete('/rules/{rule}', [NetworkController::class, 'destroy'])->name('servers.network.rules.destroy');
    });
});
