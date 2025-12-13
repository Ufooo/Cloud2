<?php

use Illuminate\Support\Facades\Route;
use Nip\Security\Http\Controllers\SiteSecurityRuleController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::middleware('server.connected')->group(function () {
        Route::prefix('/sites/{site:slug}/security')->group(function () {
            Route::get('/', [SiteSecurityRuleController::class, 'index'])->name('sites.security');
            Route::post('/', [SiteSecurityRuleController::class, 'store'])->name('sites.security.store');
            Route::patch('/{rule}', [SiteSecurityRuleController::class, 'update'])->name('sites.security.update');
            Route::delete('/{rule}', [SiteSecurityRuleController::class, 'destroy'])->name('sites.security.destroy');
        });
    });
});
