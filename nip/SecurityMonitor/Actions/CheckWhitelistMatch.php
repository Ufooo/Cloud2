<?php

namespace Nip\SecurityMonitor\Actions;

use Illuminate\Support\Collection;
use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\SecurityMonitor\Models\SecurityGitWhitelist;
use Nip\Site\Models\Site;

class CheckWhitelistMatch
{
    /**
     * Batch check if git changes are whitelisted.
     *
     * @param  array<array{file: string, type: string}>  $changes
     * @return Collection<string, bool> Map of file path to whitelisted status
     */
    public function checkGitBatch(Site $site, array $changes): Collection
    {
        $filePaths = array_column($changes, 'file');

        $whitelisted = SecurityGitWhitelist::query()
            ->where('site_id', $site->id)
            ->whereIn('file_path', $filePaths)
            ->get()
            ->keyBy('file_path');

        return collect($changes)->mapWithKeys(function ($change) use ($whitelisted) {
            $whitelist = $whitelisted->get($change['file']);
            $changeType = GitChangeType::tryFrom($change['type']) ?? GitChangeType::Unknown;

            $isWhitelisted = $whitelist && (
                $whitelist->change_type === $changeType ||
                $whitelist->change_type === GitChangeType::Any
            );

            return [$change['file'] => $isWhitelisted];
        });
    }
}
