<?php

namespace Nip\SecurityMonitor\Actions;

use Nip\SecurityMonitor\Enums\ScanStatus;
use Nip\SecurityMonitor\Events\GitChangesDetected;
use Nip\SecurityMonitor\Events\SecurityScanCompleted;
use Nip\SecurityMonitor\Models\SecurityScan;

class FinalizeScan
{
    public function handle(SecurityScan $scan): void
    {
        $scan->refresh();
        $scan->load('site');

        $hasGitIssues = $scan->git_new_count > 0;
        $hasError = $scan->error_message !== null;

        $scan->update([
            'status' => match (true) {
                $hasError && $hasGitIssues => ScanStatus::IssuesDetected,
                $hasError => ScanStatus::Error,
                $hasGitIssues => ScanStatus::IssuesDetected,
                default => ScanStatus::Clean,
            },
            'completed_at' => now(),
        ]);

        SecurityScanCompleted::dispatch($scan);

        if ($hasGitIssues) {
            GitChangesDetected::dispatch($scan, $scan->site, $scan->git_new_count);
        }
    }
}
