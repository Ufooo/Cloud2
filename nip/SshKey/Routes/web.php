<?php

use Illuminate\Support\Facades\Route;
use Nip\SshKey\Http\Controllers\SshKeyController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/servers/{server:slug}/ssh-keys', [SshKeyController::class, 'index'])
        ->name('servers.ssh-keys');
});
