<?php

use Illuminate\Support\Facades\Route;
use Nip\SecurityMonitor\Http\Controllers\GitWhitelistController;
use Nip\SecurityMonitor\Http\Controllers\SecurityMonitorController;
use Nip\SecurityMonitor\Http\Controllers\SecuritySettingsController;

Route::middleware(['web', 'auth'])->group(function () {
    // Security Monitor dashboard
    Route::prefix('security-monitor')->name('securityMonitor.')->group(function () {
        Route::get('/', [SecurityMonitorController::class, 'index'])->name('index');
        Route::post('/sites/{site}/scan', [SecurityMonitorController::class, 'scan'])->name('scan');
        Route::get('/sites/{site}/history', [SecurityMonitorController::class, 'history'])->name('history');

        Route::post('/git-whitelist', [GitWhitelistController::class, 'store'])->name('gitWhitelist.store');
        Route::delete('/git-whitelist/{whitelist}', [GitWhitelistController::class, 'destroy'])->name('gitWhitelist.destroy');
        Route::delete('/sites/{site}/git-whitelist', [GitWhitelistController::class, 'removeByFile'])->name('gitWhitelist.removeByFile');
        Route::post('/sites/{site}/git-whitelist-all', [GitWhitelistController::class, 'whitelistAll'])->name('gitWhitelist.all');
    });

    // Site-specific security monitor page (uses SiteLayout)
    Route::get('/sites/{site}/security-monitor', [SecurityMonitorController::class, 'show'])->name('sites.securityMonitor');
    Route::patch('/sites/{site}/security-monitor/settings', [SecuritySettingsController::class, 'update'])->name('sites.securityMonitor.settings');
});
