<?php

namespace Nip\Server\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Server\Models\Server;
use Nip\Shared\Traits\BuildsResourcePermissions;

class ServerListResource extends JsonResource
{
    use BuildsResourcePermissions;

    /** @var Server */
    public $resource;

    public function toArray(Request $request): array
    {
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
            'phpVersionLabel' => $this->php_version_string,
            'databaseType' => $this->database_type,
            'displayableDatabaseType' => $this->displayable_database_type,
            'avatarColor' => $this->avatar_color,
            'displayableProvider' => $this->displayable_provider,
            'isReady' => $this->is_ready,
            'lastConnectedAt' => $this->last_connected_at?->toISOString(),
            'createdAt' => $this->created_at->toISOString(),
            'can' => $this->buildPermissions($request, ['view', 'update', 'delete']),
        ];
    }
}
