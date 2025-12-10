<?php

use Illuminate\Support\Facades\Route;
use Nip\UnixUser\Http\Controllers\UnixUserController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('/servers/{server:slug}/unix-users')->group(function () {
        Route::get('/', [UnixUserController::class, 'index'])->name('servers.unix-users');
        Route::post('/', [UnixUserController::class, 'store'])->name('servers.unix-users.store');
        Route::delete('/{user}', [UnixUserController::class, 'destroy'])->name('servers.unix-users.destroy');
    });
});
