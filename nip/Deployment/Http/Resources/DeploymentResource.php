<?php

namespace Nip\Deployment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Deployment\Models\Deployment;

/**
 * @mixin Deployment
 */
class DeploymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'statusLabel' => $this->status?->label(),
            'statusColor' => $this->status?->color(),
            'commitHash' => $this->commit_hash,
            'shortCommitHash' => $this->getShortCommitHash(),
            'commitMessage' => $this->getCommitSubject(),
            'commitAuthor' => $this->commit_author,
            'branch' => $this->branch,
            'deployedBy' => $this->user?->name,
            'startedAt' => $this->started_at?->toIso8601String(),
            'endedAt' => $this->ended_at?->toIso8601String(),
            'duration' => $this->getDuration(),
            'durationForHumans' => $this->getDurationForHumans(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'createdAtForHumans' => $this->created_at?->diffForHumans(),
        ];
    }
}
