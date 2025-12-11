<?php

use Illuminate\Support\Facades\Route;
use Nip\Process\Http\Controllers\ProcessController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::middleware('server.connected')->group(function () {
        Route::get('/servers/{server:slug}/processes', [ProcessController::class, 'index'])
            ->name('servers.processes');
    });
});
