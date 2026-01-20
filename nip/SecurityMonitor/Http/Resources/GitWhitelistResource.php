<?php

namespace Nip\SecurityMonitor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\SecurityMonitor\Models\SecurityGitWhitelist;

class GitWhitelistResource extends JsonResource
{
    /** @var SecurityGitWhitelist */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filePath' => $this->file_path,
            'changeType' => $this->change_type->value,
            'changeTypeLabel' => $this->change_type->label(),
            'reason' => $this->reason,
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
