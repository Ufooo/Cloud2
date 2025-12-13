<?php

use Illuminate\Support\Facades\Route;
use Nip\Redirect\Http\Controllers\SiteRedirectRuleController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::middleware('server.connected')->group(function () {
        Route::prefix('/sites/{site:slug}/redirects')->group(function () {
            Route::get('/', [SiteRedirectRuleController::class, 'index'])->name('sites.redirects');
            Route::post('/', [SiteRedirectRuleController::class, 'store'])->name('sites.redirects.store');
            Route::patch('/{rule}', [SiteRedirectRuleController::class, 'update'])->name('sites.redirects.update');
            Route::delete('/{rule}', [SiteRedirectRuleController::class, 'destroy'])->name('sites.redirects.destroy');
        });
    });
});
