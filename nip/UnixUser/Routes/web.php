<?php

use Illuminate\Support\Facades\Route;
use Nip\UnixUser\Http\Controllers\UnixUserController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/servers/{server:slug}/unix-users', [UnixUserController::class, 'index'])
        ->name('servers.unix-users');
});
