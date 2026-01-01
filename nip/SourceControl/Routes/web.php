<?php

use Illuminate\Support\Facades\Route;
use Nip\SourceControl\Http\Controllers\SourceControlController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('/source-control', [SourceControlController::class, 'index'])->name('source-control.index');
    Route::get('/source-control/{provider}/redirect', [SourceControlController::class, 'redirect'])->name('source-control.redirect');
    Route::delete('/source-control/{sourceControl}', [SourceControlController::class, 'destroy'])->name('source-control.destroy');

    Route::get('/source-control/{sourceControl}/repositories', [SourceControlController::class, 'repositories'])->name('source-control.repositories');
    Route::get('/source-control/{sourceControl}/branches/{repository}', [SourceControlController::class, 'branches'])
        ->where('repository', '.*')
        ->name('source-control.branches');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/source-control/{provider}/callback', [SourceControlController::class, 'callback'])->name('source-control.callback');
});
