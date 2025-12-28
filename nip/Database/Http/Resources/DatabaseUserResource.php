<?php

namespace Nip\Database\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Database\Enums\DatabaseUserStatus;
use Nip\Database\Models\DatabaseUser;

/**
 * @mixin DatabaseUser
 */
class DatabaseUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->server);
        $isInstalled = $this->status === DatabaseUserStatus::Installed;

        return [
            'id' => $this->id,
            'serverId' => $this->server_id,
            'serverName' => $this->whenLoaded('server', fn () => $this->server->name),
            'serverSlug' => $this->whenLoaded('server', fn () => $this->server->slug),
            'username' => $this->username,
            'readonly' => $this->readonly,
            'status' => $this->status?->value,
            'displayableStatus' => $this->status?->label(),
            'statusBadgeVariant' => $this->status?->badgeVariant(),
            'databaseCount' => $this->whenCounted('databases'),
            'databaseIds' => $this->whenLoaded('databases', fn () => $this->databases->pluck('id')->toArray()),
            'createdAt' => $this->created_at?->toISOString(),
            'createdAtHuman' => $this->created_at?->diffForHumans(),
            'can' => [
                'update' => $canUpdate && $isInstalled,
                'delete' => $canUpdate
                    && $this->status !== DatabaseUserStatus::Installing
                    && $this->status !== DatabaseUserStatus::Syncing
                    && $this->status !== DatabaseUserStatus::Deleting,
            ],
        ];
    }
}
