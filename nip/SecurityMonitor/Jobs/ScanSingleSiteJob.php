<?php

namespace Nip\SecurityMonitor\Jobs;

use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nip\SecurityMonitor\Actions\FinalizeScan;
use Nip\SecurityMonitor\Actions\ProcessGitStatus;
use Nip\SecurityMonitor\Enums\ScanStatus;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Models\Site;

class ScanSingleSiteJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120; // 2 minutes per site

    public function __construct(
        public int $scanId,
    ) {}

    public function handle(
        ProcessGitStatus $processGitStatus,
        FinalizeScan $finalizeScan,
        SSHService $sshService,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $scan = SecurityScan::find($this->scanId);
        if (! $scan) {
            return;
        }

        // Mark as running
        $scan->update(['status' => ScanStatus::Running]);

        $site = $scan->site;
        $server = $scan->server;

        if (! $site || ! $server) {
            $scan->update([
                'status' => ScanStatus::Error,
                'error_message' => 'Site or server not found',
                'completed_at' => now(),
            ]);

            return;
        }

        try {
            $sshService->connect($server);
            $sshService->setTimeout(60);

            // Run git scan if enabled
            if ($site->git_monitor_enabled) {
                $this->runGitScan($site, $scan, $sshService, $processGitStatus);
            }
        } catch (Exception $e) {
            $existingError = $scan->error_message;
            $scan->update([
                'error_message' => $existingError
                    ? "{$existingError}\nConnection error: ".$e->getMessage()
                    : 'Connection error: '.$e->getMessage(),
            ]);
        } finally {
            $sshService->disconnect();
            $finalizeScan->handle($scan);
        }
    }

    private function runGitScan(Site $site, SecurityScan $scan, SSHService $sshService, ProcessGitStatus $processor): void
    {
        try {
            $path = $site->getProjectPath();

            $script = view('provisioning.scripts.security.git-scan', [
                'paths' => [$path],
            ])->render();

            $result = $sshService->executeScript($script);

            if (! $result->isSuccessful()) {
                throw new Exception("Git scan failed with exit code {$result->exitCode}");
            }

            $processor->handle($scan, $site, $result->output);
        } catch (Exception $e) {
            $scan->update([
                'error_message' => 'Git error: '.$e->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        return ['security-scan', "scan:{$this->scanId}"];
    }
}
