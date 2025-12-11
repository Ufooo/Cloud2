<?php

use Illuminate\Support\Facades\Route;
use Nip\SshKey\Http\Controllers\SshKeyController;
use Nip\SshKey\Http\Controllers\UserSshKeyController;

Route::middleware(['web', 'auth'])->group(function () {
    // Server SSH keys
    Route::middleware('server.connected')->group(function () {
        Route::get('/servers/{server:slug}/ssh-keys', [SshKeyController::class, 'index'])
            ->name('servers.ssh-keys');

        Route::post('/servers/{server:slug}/ssh-keys', [SshKeyController::class, 'store'])
            ->name('servers.ssh-keys.store');

        Route::delete('/servers/{server:slug}/ssh-keys/{sshKey}', [SshKeyController::class, 'destroy'])
            ->name('servers.ssh-keys.destroy');
    });

    // User/account SSH keys
    Route::prefix('/settings/ssh-keys')->group(function () {
        Route::get('/', [UserSshKeyController::class, 'index'])->name('settings.ssh-keys');
        Route::post('/', [UserSshKeyController::class, 'store'])->name('settings.ssh-keys.store');
        Route::delete('/{key}', [UserSshKeyController::class, 'destroy'])->name('settings.ssh-keys.destroy');
    });
});
