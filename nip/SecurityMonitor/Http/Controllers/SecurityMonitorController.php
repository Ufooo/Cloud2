<?php

namespace Nip\SecurityMonitor\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\SecurityMonitor\Data\SecuritySettingsData;
use Nip\SecurityMonitor\Enums\ScanStatus;
use Nip\SecurityMonitor\Http\Resources\GitChangeResource;
use Nip\SecurityMonitor\Http\Resources\SecurityScanResource;
use Nip\SecurityMonitor\Http\Resources\ServerGroupResource;
use Nip\SecurityMonitor\Jobs\RunSecurityScanJob;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Server\Models\Server;
use Nip\Site\Data\SiteData;
use Nip\Site\Models\Site;

class SecurityMonitorController extends Controller
{
    public function index(): Response
    {
        $servers = Server::query()
            ->with(['sites' => function ($query) {
                $query->where('git_monitor_enabled', true)
                    ->with(['latestSecurityScan', 'server']);
            }])
            ->get();

        $allSites = $servers->flatMap->sites;

        $summary = [
            'totalSites' => $allSites->count(),
            'cleanSites' => $allSites->filter(fn ($site) => $site->latestSecurityScan?->status === ScanStatus::Clean)->count(),
            'gitIssuesSites' => $allSites->filter(fn ($site) => ($site->latestSecurityScan?->git_new_count ?? 0) > 0)->count(),
            'errorSites' => $allSites->filter(fn ($site) => $site->latestSecurityScan?->status === ScanStatus::Error)->count(),
        ];

        // Filter to only show sites with issues (git changes or errors)
        $serversWithIssues = $servers->map(function ($server) {
            $server->setRelation('sites', $server->sites->filter(function ($site) {
                $scan = $site->latestSecurityScan;
                if (! $scan) {
                    return false;
                }

                return $scan->git_new_count > 0
                    || $scan->status === ScanStatus::Error;
            })->values());

            return $server;
        })->filter(fn ($server) => $server->sites->isNotEmpty())->values();

        return Inertia::render('security-monitor/index/page', [
            'serverGroups' => ServerGroupResource::collection($serversWithIssues),
            'summary' => $summary,
        ]);
    }

    public function show(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $site->load([
            'server',
            'latestSecurityScan.gitChanges',
            'gitWhitelists',
        ]);

        return Inertia::render('security-monitor/show/page', [
            'site' => SiteData::fromModel($site),
            'lastScan' => $site->latestSecurityScan ? SecurityScanResource::make($site->latestSecurityScan) : null,
            'gitChanges' => $site->latestSecurityScan
                ? GitChangeResource::collection($site->latestSecurityScan->gitChanges)
                : [],
            'securitySettings' => SecuritySettingsData::fromSite($site),
        ]);
    }

    public function scan(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        Bus::batch([
            new RunSecurityScanJob($site->server_id, [$site->id]),
        ])->name("Security scan: {$site->domain}")->dispatch();

        return redirect()->back()->with('success', 'Security scan started.');
    }

    public function history(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->server);

        $scans = SecurityScan::query()
            ->where('site_id', $site->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(SecurityScanResource::collection($scans));
    }
}
