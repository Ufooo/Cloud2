<?php

namespace Nip\SecurityMonitor\Console\Commands;

use Illuminate\Console\Command;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Site\Models\Site;

class SecurityStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'security:status
        {--site= : Show status for specific site ID}';

    /**
     * The console command description.
     */
    protected $description = 'Show security monitoring status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($siteId = $this->option('site')) {
            return $this->showSiteStatus((int) $siteId);
        }

        return $this->showSummary();
    }

    /**
     * Show overall security monitoring summary.
     */
    private function showSummary(): int
    {
        $totalSites = Site::query()
            ->where('git_monitor_enabled', true)
            ->count();

        $scansToday = SecurityScan::query()
            ->whereDate('created_at', today())
            ->count();

        $issuesDetected = SecurityScan::query()
            ->whereDate('created_at', today())
            ->where('status', 'issues_detected')
            ->count();

        $runningScans = SecurityScan::query()
            ->where('status', 'running')
            ->count();

        $this->newLine();
        $this->info('Security Monitoring Summary');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Monitored Sites', $totalSites],
                ['Scans Today', $scansToday],
                ['Sites with Issues', $issuesDetected],
                ['Running Scans', $runningScans],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Show detailed status for a specific site.
     */
    private function showSiteStatus(int $siteId): int
    {
        $site = Site::with('latestSecurityScan')->findOrFail($siteId);
        $scan = $site->latestSecurityScan;

        $this->newLine();
        $this->info("Security Status for Site: {$site->domain}");
        $this->newLine();

        $this->table(
            ['Setting', 'Value'],
            [
                ['Git Monitor', $site->git_monitor_enabled ? '✓ Enabled' : '✗ Disabled'],
                ['Scan Interval', "{$site->security_scan_interval_minutes} minutes"],
                ['Retention Period', "{$site->security_scan_retention_days} days"],
            ]
        );

        if ($scan) {
            $this->newLine();
            $this->info('Last Scan Results');
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Scan Time', $scan->created_at->diffForHumans()],
                    ['Status', $scan->status->label()],
                    ['Duration', $scan->completed_at ? $scan->created_at->diffInSeconds($scan->completed_at).'s' : 'Running'],
                    ['Git Changes (New)', $scan->git_new_count],
                    ['Git Changes (Whitelisted)', $scan->git_whitelisted_count],
                ]
            );

            if ($scan->error_message) {
                $this->newLine();
                $this->error('Error Message:');
                $this->line($scan->error_message);
            }
        } else {
            $this->newLine();
            $this->warn('No scans found for this site.');
        }

        return self::SUCCESS;
    }
}
