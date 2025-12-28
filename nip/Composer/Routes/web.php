<?php

use Illuminate\Support\Facades\Route;
use Nip\Composer\Http\Controllers\ComposerController;
use Nip\Composer\Http\Controllers\ServerComposerController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    // Site composer credentials
    Route::get('sites/{site}/composer', [ComposerController::class, 'index'])
        ->name('sites.composer');

    Route::post('sites/{site}/composer/credentials', [ComposerController::class, 'store'])
        ->name('sites.composer.credentials.store');

    Route::put('sites/{site}/composer/credentials/{credential}', [ComposerController::class, 'update'])
        ->name('sites.composer.credentials.update');

    Route::delete('sites/{site}/composer/credentials/{credential}', [ComposerController::class, 'destroy'])
        ->name('sites.composer.credentials.destroy');

    // Server composer credentials
    Route::get('servers/{server}/composer', [ServerComposerController::class, 'index'])
        ->name('servers.composer');

    Route::post('servers/{server}/composer/credentials', [ServerComposerController::class, 'store'])
        ->name('servers.composer.credentials.store');

    Route::put('servers/{server}/composer/credentials/{credential}', [ServerComposerController::class, 'update'])
        ->name('servers.composer.credentials.update');

    Route::delete('servers/{server}/composer/credentials/{credential}', [ServerComposerController::class, 'destroy'])
        ->name('servers.composer.credentials.destroy');
});
