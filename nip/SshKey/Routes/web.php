<?php

use Illuminate\Support\Facades\Route;
use Nip\SshKey\Http\Controllers\SshKeyController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/servers/{server:slug}/ssh-keys', [SshKeyController::class, 'index'])
        ->name('servers.ssh-keys');

    Route::post('/servers/{server:slug}/ssh-keys', [SshKeyController::class, 'store'])
        ->name('servers.ssh-keys.store');

    Route::delete('/servers/{server:slug}/ssh-keys/{sshKey}', [SshKeyController::class, 'destroy'])
        ->name('servers.ssh-keys.destroy');
});
