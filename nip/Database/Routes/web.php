<?php

use Illuminate\Support\Facades\Route;
use Nip\Database\Http\Controllers\DatabaseController;

Route::get('/databases', [DatabaseController::class, 'index'])->name('databases');

Route::middleware('server.connected')->group(function () {
    Route::prefix('/servers/{server:slug}/databases')->group(function () {
        Route::get('/', [DatabaseController::class, 'indexForServer'])->name('servers.databases');
        Route::post('/', [DatabaseController::class, 'store'])->name('servers.databases.store');
        Route::delete('/{database}', [DatabaseController::class, 'destroy'])->name('servers.databases.destroy');
        Route::post('/users', [DatabaseController::class, 'storeUser'])->name('servers.databases.users.store');
        Route::put('/users/{databaseUser}', [DatabaseController::class, 'updateUser'])->name('servers.databases.users.update');
        Route::delete('/users/{databaseUser}', [DatabaseController::class, 'destroyUser'])->name('servers.databases.users.destroy');
    });

    Route::get('/sites/{site:slug}/databases', [DatabaseController::class, 'indexForSite'])->name('sites.databases');
});
