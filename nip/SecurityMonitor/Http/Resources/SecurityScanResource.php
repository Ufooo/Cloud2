<?php

namespace Nip\SecurityMonitor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\SecurityMonitor\Models\SecurityScan;

class SecurityScanResource extends JsonResource
{
    /** @var SecurityScan */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'statusBadgeVariant' => $this->status->badgeVariant(),
            'gitModifiedCount' => $this->git_modified_count,
            'gitUntrackedCount' => $this->git_untracked_count,
            'gitDeletedCount' => $this->git_deleted_count,
            'gitWhitelistedCount' => $this->git_whitelisted_count,
            'gitNewCount' => $this->git_new_count,
            'errorMessage' => $this->error_message,
            'startedAt' => $this->started_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
            'completedAtHuman' => $this->completed_at?->diffForHumans(),
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
