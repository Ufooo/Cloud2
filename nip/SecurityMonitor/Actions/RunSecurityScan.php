<?php

namespace Nip\SecurityMonitor\Actions;

use Exception;
use Illuminate\Support\Collection;
use Nip\SecurityMonitor\Enums\ScanStatus;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Models\Site;

class RunSecurityScan
{
    public function __construct(
        private ProcessGitStatus $processGitStatus,
        private FinalizeScan $finalizeScan,
        private SSHService $sshService,
    ) {}

    /** @param array<int> $siteIds */
    public function handle(Server $server, array $siteIds): void
    {
        $sites = Site::query()
            ->whereIn('id', $siteIds)
            ->where('server_id', $server->id)
            ->get();

        // Create pending scans for all sites
        $scans = $sites->mapWithKeys(fn (Site $site) => [
            $site->id => SecurityScan::create([
                'site_id' => $site->id,
                'server_id' => $server->id,
                'status' => ScanStatus::Running,
                'started_at' => now(),
            ]),
        ]);

        try {
            // Establish SSH connection
            $this->sshService->connect($server);
            $this->sshService->setTimeout(300); // 5 minutes timeout for scans

            // Run git scan if any site has git monitoring enabled
            $gitEnabledSites = $sites->filter(fn ($site) => $site->git_monitor_enabled);
            if ($gitEnabledSites->isNotEmpty()) {
                $this->runGitScan($gitEnabledSites, $scans);
            }
        } finally {
            $this->sshService->disconnect();

            $scans->each(fn (SecurityScan $scan) => $this->finalizeScan->handle($scan));
        }
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @param  Collection<int, SecurityScan>  $scans
     */
    private function runGitScan(Collection $sites, Collection $scans): void
    {
        // Build site data for git scan (path + user for each site)
        $siteData = $sites->map(fn ($site) => [
            'path' => $site->getProjectPath(),
            'user' => $site->user,
        ])->values()->toArray();

        // Generate the git scan script with site data
        $script = view('provisioning.scripts.security.git-scan', [
            'sites' => $siteData,
        ])->render();

        try {
            $result = $this->sshService->executeScript($script);

            if (! $result->isSuccessful()) {
                throw new Exception("Git scan command failed with exit code {$result->exitCode}");
            }

            $sites->each(fn (Site $site) => $this->processGitStatus->handle($scans[$site->id], $site, $result->output));
        } catch (Exception $e) {
            $sites->each(fn (Site $site) => $scans[$site->id]->update([
                'status' => ScanStatus::Error,
                'error_message' => 'Git scan failed: '.$e->getMessage(),
            ]));
        }
    }
}
