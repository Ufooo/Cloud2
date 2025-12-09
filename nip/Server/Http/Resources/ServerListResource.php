<?php

namespace Nip\Server\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Server\Models\Server;

class ServerListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Server $this */
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'provider' => $this->provider,
            'type' => $this->type,
            'displayableType' => $this->displayable_type,
            'status' => $this->status,
            'ipAddress' => $this->ip_address,
            'phpVersion' => $this->php_version,
            'displayablePhpVersion' => $this->displayable_php_version,
            'databaseType' => $this->database_type,
            'displayableDatabaseType' => $this->displayable_database_type,
            'avatarColor' => $this->avatar_color,
            'displayableProvider' => $this->displayable_provider,
            'isReady' => $this->is_ready,
            'lastConnectedAt' => $this->last_connected_at?->toISOString(),
            'createdAt' => $this->created_at->toISOString(),
            'can' => [
                'view' => $user?->can('view', $this->resource) ?? false,
                'update' => $user?->can('update', $this->resource) ?? false,
                'delete' => $user?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
