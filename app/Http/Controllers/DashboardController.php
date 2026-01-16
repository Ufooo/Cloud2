<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Http\Resources\ExpiringCertificateResource;
use Nip\Domain\Models\Certificate;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $expiringCertificates = Certificate::query()
            ->where('status', CertificateStatus::Installed)
            ->where('active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->with('site')
            ->orderBy('expires_at')
            ->get();

        return Inertia::render('Dashboard', [
            'expiringCertificates' => ExpiringCertificateResource::collection($expiringCertificates),
        ]);
    }
}
