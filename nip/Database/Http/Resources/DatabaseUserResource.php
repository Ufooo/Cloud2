<?php

namespace Nip\Database\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
        return [
            'id' => $this->id,
            'serverId' => $this->server_id,
            'serverName' => $this->whenLoaded('server', fn () => $this->server->name),
            'username' => $this->username,
            'readonly' => $this->readonly,
            'databaseCount' => $this->whenCounted('databases'),
            'databaseIds' => $this->whenLoaded('databases', fn () => $this->databases->pluck('id')->toArray()),
            'createdAt' => $this->created_at?->toISOString(),
            'createdAtHuman' => $this->created_at?->diffForHumans(),
        ];
    }
}
