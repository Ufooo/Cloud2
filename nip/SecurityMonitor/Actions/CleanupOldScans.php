<?php

namespace Nip\SecurityMonitor\Actions;

use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Site\Models\Site;

class CleanupOldScans
{
    /**
     * Cleanup old scans for all sites based on their retention settings.
     *
     * This will iterate through all sites that have security monitoring enabled
     * and delete scans older than the configured retention period.
     *
     * @return int Total number of scans deleted
     */
    public function handle(): int
    {
        $deletedCount = 0;

        Site::query()
            ->where('git_monitor_enabled', true)
            ->chunk(100, function ($sites) use (&$deletedCount) {
                $deletedCount += $sites->sum(fn (Site $site) => $this->cleanupForSite($site));
            });

        return $deletedCount;
    }

    /**
     * Cleanup old scans for a specific site.
     *
     * Uses the site's security_scan_retention_days setting (defaults to 7 days)
     * to determine which scans should be deleted.
     *
     * @param  Site  $site  The site to cleanup scans for
     * @return int Number of scans deleted for this site
     */
    public function cleanupForSite(Site $site): int
    {
        $retentionDays = $site->security_scan_retention_days ?? 7;
        $cutoffDate = now()->subDays($retentionDays);

        return SecurityScan::query()
            ->where('site_id', $site->id)
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }
}
