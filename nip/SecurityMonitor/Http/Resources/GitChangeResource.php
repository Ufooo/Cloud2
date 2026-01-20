<?php

namespace Nip\SecurityMonitor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\SecurityMonitor\Models\SecurityGitChange;

class GitChangeResource extends JsonResource
{
    /** @var SecurityGitChange */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filePath' => $this->file_path,
            'changeType' => $this->change_type->value,
            'changeTypeLabel' => $this->change_type->label(),
            'changeTypeBadgeVariant' => $this->change_type->badgeVariant(),
            'gitStatusCode' => $this->git_status_code,
            'isWhitelisted' => $this->is_whitelisted,
            'whitelistReason' => $this->whitelist_reason,
            'whitelistedAt' => $this->whitelisted_at?->toISOString(),
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
