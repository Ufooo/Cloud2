<?php

namespace Nip\SecurityMonitor\Actions;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Nip\SecurityMonitor\Enums\ScanStatus;
use Nip\SecurityMonitor\Jobs\ScanSingleSiteJob;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

class DispatchSecurityScanBatch
{
    /**
     * Dispatch a batch of security scans for sites on a server.
     *
     * Each site gets its own job running in parallel.
     * Returns the batch so progress can be tracked.
     *
     * @param  Server  $server  The server to scan sites on
     * @param  array<int>|null  $siteIds  Site IDs to scan (null = all sites on server)
     * @return Batch The dispatched batch
     */
    public function handle(Server $server, ?array $siteIds = null): Batch
    {
        $jobs = Site::query()
            ->where('server_id', $server->id)
            ->when($siteIds, fn ($query) => $query->whereIn('id', $siteIds))
            ->get()
            ->map(fn (Site $site) => SecurityScan::create([
                'site_id' => $site->id,
                'server_id' => $server->id,
                'status' => ScanStatus::Pending,
                'started_at' => now(),
            ]))
            ->map(fn (SecurityScan $scan) => new ScanSingleSiteJob($scan->id));

        return Bus::batch($jobs)
            ->name("Security Scan: {$server->name}")
            ->allowFailures()
            ->dispatch();
    }
}
