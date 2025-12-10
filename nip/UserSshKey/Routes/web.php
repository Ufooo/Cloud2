<?php

use Illuminate\Support\Facades\Route;
use Nip\UserSshKey\Http\Controllers\UserSshKeyController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('/settings/ssh-keys')->group(function () {
        Route::get('/', [UserSshKeyController::class, 'index'])->name('settings.ssh-keys');
        Route::post('/', [UserSshKeyController::class, 'store'])->name('settings.ssh-keys.store');
        Route::delete('/{key}', [UserSshKeyController::class, 'destroy'])->name('settings.ssh-keys.destroy');
    });
});
