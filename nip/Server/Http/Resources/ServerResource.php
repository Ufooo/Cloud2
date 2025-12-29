<?php

namespace Nip\Server\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Server\Models\Server;

class ServerResource extends JsonResource
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
            'providerServerId' => $this->provider_server_id,
            'type' => $this->type,
            'displayableType' => $this->displayable_type,
            'status' => $this->status,
            'ipAddress' => $this->ip_address,
            'privateIpAddress' => $this->private_ip_address,
            'sshPort' => $this->ssh_port,
            'phpVersion' => $this->php_version,
            'displayablePhpVersion' => $this->displayable_php_version,
            'databaseType' => $this->database_type,
            'dbStatus' => $this->db_status,
            'ubuntuVersion' => $this->ubuntu_version,
            'timezone' => $this->timezone,
            'notes' => $this->notes,
            'avatarColor' => $this->avatar_color,
            'services' => $this->services,
            'displayableProvider' => $this->displayable_provider,
            'displayableDatabaseType' => $this->displayable_database_type,
            'cloudProviderUrl' => $this->cloud_provider_url,
            'isReady' => $this->is_ready,
            'gitPublicKey' => $this->git_public_key,
            'lastConnectedAt' => $this->last_connected_at?->toISOString(),
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
            'can' => [
                'view' => $user?->can('view', $this->resource) ?? false,
                'update' => $user?->can('update', $this->resource) ?? false,
                'delete' => $user?->can('delete', $this->resource) ?? false,
                'archive' => $user?->can('archive', $this->resource) ?? false,
                'reboot' => $user?->can('reboot', $this->resource) ?? false,
            ],
        ];
    }
}
