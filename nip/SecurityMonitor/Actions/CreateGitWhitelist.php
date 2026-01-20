<?php

namespace Nip\SecurityMonitor\Actions;

use Illuminate\Support\Facades\Auth;
use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\SecurityMonitor\Models\SecurityGitChange;
use Nip\SecurityMonitor\Models\SecurityGitWhitelist;
use Nip\Site\Models\Site;

class CreateGitWhitelist
{
    public function handle(
        Site $site,
        string $filePath,
        GitChangeType $changeType = GitChangeType::Any,
        ?string $reason = null,
    ): SecurityGitWhitelist {
        $whitelist = SecurityGitWhitelist::create([
            'site_id' => $site->id,
            'file_path' => $filePath,
            'change_type' => $changeType,
            'created_by' => Auth::id(),
            'reason' => $reason,
        ]);

        $this->updateExistingChanges($site, $filePath, $changeType, $reason);

        return $whitelist;
    }

    private function updateExistingChanges(
        Site $site,
        string $filePath,
        GitChangeType $changeType,
        ?string $reason,
    ): void {
        $query = SecurityGitChange::query()
            ->whereHas('scan', fn ($q) => $q->where('site_id', $site->id))
            ->where('file_path', $filePath)
            ->where('is_whitelisted', false);

        if ($changeType !== GitChangeType::Any) {
            $query->where('change_type', $changeType);
        }

        $query->update([
            'is_whitelisted' => true,
            'whitelist_reason' => $reason,
            'whitelisted_at' => now(),
        ]);
    }
}
