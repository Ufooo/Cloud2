<?php

use Illuminate\Support\Facades\Route;
use Nip\Php\Http\Controllers\PhpController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/servers/{server:slug}/php', [PhpController::class, 'index'])
        ->name('servers.php');
});
