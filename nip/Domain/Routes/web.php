<?php

use Illuminate\Support\Facades\Route;
use Nip\Domain\Http\Controllers\CertificateController;
use Nip\Domain\Http\Controllers\DomainRecordController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::prefix('sites/{site}')->group(function () {
        Route::get('domains', [DomainRecordController::class, 'index'])->name('sites.domains.index');
        Route::post('domains', [DomainRecordController::class, 'store'])->name('sites.domains.store');
        Route::patch('domains/{domainRecord}', [DomainRecordController::class, 'update'])->name('sites.domains.update');
        Route::delete('domains/{domainRecord}', [DomainRecordController::class, 'destroy'])->name('sites.domains.destroy');
        Route::post('domains/{domainRecord}/primary', [DomainRecordController::class, 'markAsPrimary'])->name('sites.domains.primary');

        Route::post('certificates', [CertificateController::class, 'store'])->name('sites.certificates.store');
        Route::delete('certificates/{certificate}', [CertificateController::class, 'destroy'])->name('sites.certificates.destroy');
        Route::post('certificates/{certificate}/activate', [CertificateController::class, 'activate'])->name('sites.certificates.activate');
        Route::post('certificates/{certificate}/deactivate', [CertificateController::class, 'deactivate'])->name('sites.certificates.deactivate');
        Route::post('certificates/{certificate}/renew', [CertificateController::class, 'renew'])->name('sites.certificates.renew');
    });
});
