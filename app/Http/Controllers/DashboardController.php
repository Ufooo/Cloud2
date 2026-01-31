<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Http\Resources\ExpiringCertificateResource;
use Nip\Domain\Models\Certificate;
use Nip\Server\Http\Resources\ServerWidgetResource;
use Nip\Server\Models\Server;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $expiringCertificates = Certificate::query()
            ->whereIn('status', [CertificateStatus::Installed, CertificateStatus::Renewing])
            ->where('active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->with('site')
            ->orderBy('expires_at')
            ->get();

        $servers = Server::query()
            ->withCount('sites')
            ->orderBy('name')
            ->get();

        return Inertia::render('Dashboard', [
            'expiringCertificates' => ExpiringCertificateResource::collection($expiringCertificates),
            'servers' => ServerWidgetResource::collection($servers),
        ]);
    }
}
