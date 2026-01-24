<?php

namespace Nip\SecurityMonitor\Actions;

use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\SecurityMonitor\Models\SecurityGitChange;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\SecurityMonitor\Services\GitStatusParser;
use Nip\Site\Models\Site;

class ProcessGitStatus
{
    public function __construct(
        private GitStatusParser $parser,
        private CheckWhitelistMatch $checkWhitelist,
    ) {}

    /**
     * Paths that are symlinked in zero-downtime deployments.
     * These appear as deleted/untracked but are expected behavior.
     */
    private const ZD_SYMLINK_PATHS = [
        'storage',
        'bootstrap/cache',
    ];

    public function handle(SecurityScan $scan, Site $site, string $gitOutput): void
    {
        $parsedData = $this->parser->parse($gitOutput);

        $error = $this->parser->getSiteError($parsedData, $site->getProjectPath());
        if ($error) {
            $scan->update(['error_message' => "Git error: {$error}"]);

            return;
        }

        $changes = $this->parser->getChangesForSite($parsedData, $site->getProjectPath());

        if ($site->zero_downtime) {
            $changes = $this->filterZeroDowntimeSymlinks($changes);
        }

        if (empty($changes)) {
            return;
        }

        $whitelistedMap = $this->checkWhitelist->checkGitBatch($site, $changes);

        $counts = [
            'modified' => 0,
            'untracked' => 0,
            'deleted' => 0,
            'whitelisted' => 0,
            'new' => 0,
        ];

        $gitChanges = [];
        $now = now();

        foreach ($changes as $change) {
            $changeType = GitChangeType::tryFrom($change['type']) ?? GitChangeType::Unknown;
            $isWhitelisted = $whitelistedMap->get($change['file'], false);

            $gitChanges[] = [
                'scan_id' => $scan->id,
                'site_id' => $site->id,
                'file_path' => $change['file'],
                'change_type' => $changeType->value,
                'git_status_code' => $change['status'],
                'is_whitelisted' => $isWhitelisted,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $countKey = match ($changeType) {
                GitChangeType::Modified => 'modified',
                GitChangeType::Untracked => 'untracked',
                GitChangeType::Deleted => 'deleted',
                default => null,
            };

            if ($countKey) {
                $counts[$countKey]++;
            }

            $isWhitelisted ? $counts['whitelisted']++ : $counts['new']++;
        }

        SecurityGitChange::insert($gitChanges);

        $scan->update([
            'git_modified_count' => $counts['modified'],
            'git_untracked_count' => $counts['untracked'],
            'git_deleted_count' => $counts['deleted'],
            'git_whitelisted_count' => $counts['whitelisted'],
            'git_new_count' => $counts['new'],
        ]);
    }

    /**
     * Filter out paths that are symlinked in zero-downtime deployments.
     *
     * @param  array<array{file: string, type: string, status: string}>  $changes
     * @return array<array{file: string, type: string, status: string}>
     */
    private function filterZeroDowntimeSymlinks(array $changes): array
    {
        return array_filter($changes, function (array $change): bool {
            $file = $change['file'];

            foreach (self::ZD_SYMLINK_PATHS as $symlinkPath) {
                if ($file === $symlinkPath || str_starts_with($file, $symlinkPath.'/')) {
                    return false;
                }
            }

            return true;
        });
    }
}
