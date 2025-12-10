<?php

use Illuminate\Support\Facades\Route;
use Nip\Network\Http\Controllers\NetworkController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/servers/{server:slug}/network', [NetworkController::class, 'index'])
        ->name('servers.network');
});
