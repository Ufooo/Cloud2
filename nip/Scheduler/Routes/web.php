<?php

use Illuminate\Support\Facades\Route;
use Nip\Scheduler\Http\Controllers\SchedulerController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/servers/{server:slug}/scheduler', [SchedulerController::class, 'index'])
        ->name('servers.scheduler');
});
