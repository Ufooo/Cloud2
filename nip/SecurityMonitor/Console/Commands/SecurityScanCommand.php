<?php

namespace Nip\SecurityMonitor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Nip\SecurityMonitor\Jobs\RunSecurityScanJob;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

class SecurityScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'security:scan
        {--site= : Scan specific site ID}
        {--server= : Scan all monitored sites on specific server ID}';

    /**
     * The console command description.
     */
    protected $description = 'Run security scans on sites that need scanning';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // If specific site requested
        if ($siteId = $this->option('site')) {
            return $this->scanSite((int) $siteId);
        }

        // If specific server requested
        if ($serverId = $this->option('server')) {
            return $this->scanServer((int) $serverId);
        }

        // Default: scan all sites that need it based on interval
        return $this->scanAllDueSites();
    }

    /**
     * Scan a specific site.
     */
    private function scanSite(int $siteId): int
    {
        $site = Site::findOrFail($siteId);

        if (! $site->git_monitor_enabled) {
            $this->warn("Site '{$site->domain}' has no monitoring enabled.");

            return self::FAILURE;
        }

        if (empty($site->repository)) {
            $this->warn("Site '{$site->domain}' has no repository configured.");

            return self::FAILURE;
        }

        Bus::batch([
            new RunSecurityScanJob($site->server_id, [$site->id]),
        ])->name("Security scan: {$site->domain}")->dispatch();

        $this->info("Dispatched security scan for site: {$site->domain}");

        return self::SUCCESS;
    }

    /**
     * Scan all monitored sites on a specific server.
     */
    private function scanServer(int $serverId): int
    {
        $sites = Site::query()
            ->where('server_id', $serverId)
            ->where('git_monitor_enabled', true)
            ->whereNotNull('repository')
            ->where('repository', '!=', '')
            ->get();

        if ($sites->isEmpty()) {
            $this->warn('No sites with monitoring enabled on this server.');

            return self::SUCCESS;
        }

        $server = Server::findOrFail($serverId);

        Bus::batch([
            new RunSecurityScanJob($serverId, $sites->pluck('id')->toArray()),
        ])->name("Security scan: {$server->name} ({$sites->count()} sites)")->dispatch();

        $this->info("Dispatched security scan for {$sites->count()} sites on server #{$serverId}");

        return self::SUCCESS;
    }

    /**
     * Scan all sites that are due for scanning based on their interval.
     */
    private function scanAllDueSites(): int
    {
        $sitesToScan = Site::query()
            ->where('git_monitor_enabled', true)
            ->whereNotNull('repository')
            ->where('repository', '!=', '')
            ->where(function ($query) {
                // Sites that have never been scanned
                $query->whereDoesntHave('latestSecurityScan')
                    // Or sites where the last scan is older than the interval
                    ->orWhereHas('latestSecurityScan', function ($subQuery) {
                        $subQuery->whereRaw(
                            'DATE_ADD(completed_at, INTERVAL sites.security_scan_interval_minutes MINUTE) < NOW()'
                        );
                    });
            })
            ->with('server')
            ->get();

        if ($sitesToScan->isEmpty()) {
            $this->info('No sites due for scanning.');

            return self::SUCCESS;
        }

        // Group sites by server for efficient batch processing
        $sitesByServer = $sitesToScan->groupBy('server_id');

        $jobs = $sitesByServer->map(
            fn ($sites, $serverId) => new RunSecurityScanJob($serverId, $sites->pluck('id')->toArray())
        );

        Bus::batch($jobs)
            ->name("Security scan: {$sitesToScan->count()} sites across {$sitesByServer->count()} servers")
            ->dispatch();

        $this->info("Dispatched scans for {$sitesToScan->count()} sites across {$sitesByServer->count()} servers.");

        return self::SUCCESS;
    }
}
