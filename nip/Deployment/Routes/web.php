<?php

use Illuminate\Support\Facades\Route;
use Nip\Deployment\Http\Controllers\SiteDeploymentController;

Route::middleware('server.connected')->group(function () {
    Route::prefix('/sites/{site:slug}/deployments')->group(function () {
        Route::get('/', [SiteDeploymentController::class, 'index'])->name('sites.deployments');
        Route::get('/settings', [SiteDeploymentController::class, 'settings'])->name('sites.deployments.settings');
        Route::patch('/settings', [SiteDeploymentController::class, 'updateSettings'])->name('sites.deployments.settings.update');
        Route::post('/settings/regenerate-token', [SiteDeploymentController::class, 'regenerateToken'])->name('sites.deployments.regenerate-token');
    });
});
