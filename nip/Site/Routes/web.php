<?php

use Illuminate\Support\Facades\Route;
use Nip\Server\Http\Controllers\ProvisionScriptController;
use Nip\Site\Http\Controllers\ServerSiteController;
use Nip\Site\Http\Controllers\SiteController;
use Nip\Site\Http\Controllers\SiteEnvironmentController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    // Global sites list
    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::get('/sites/create/{type}', [SiteController::class, 'create'])->name('sites.create');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');

    // Server-filtered sites list
    Route::get('/servers/{server}/sites', [ServerSiteController::class, 'index'])->name('servers.sites');

    // Site-specific routes
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
    Route::get('/sites/{site}/settings', [SiteController::class, 'settings'])->name('sites.settings');

    // Environment management
    Route::get('/sites/{site}/environment', [SiteEnvironmentController::class, 'show'])->name('sites.environment');
    Route::get('/sites/{site}/environment/content', [SiteEnvironmentController::class, 'get'])->name('sites.environment.get');
    Route::put('/sites/{site}/environment', [SiteEnvironmentController::class, 'update'])->name('sites.environment.update');

    Route::get('/sites/{site}/failed-scripts', [ProvisionScriptController::class, 'failedForSite'])
        ->name('sites.failed-scripts');
    Route::match(['put', 'patch'], '/sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
    Route::post('/sites/{site}/deploy', [SiteController::class, 'deploy'])->name('sites.deploy');
    Route::post('/sites/{site}/detect-packages', [SiteController::class, 'detectPackages'])->name('sites.detectPackages');
    Route::post('/sites/{site}/enable-ssr', [SiteController::class, 'enableSSR'])->name('sites.enableSSR');
    Route::post('/sites/{site}/disable-ssr', [SiteController::class, 'disableSSR'])->name('sites.disableSSR');
});
