<?php

namespace Nip\Server\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Server\Models\ProvisionScript;

class ProvisionScriptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ProvisionScript $this */
        return [
            'id' => $this->id,
            'serverId' => $this->server_id,
            'serverName' => $this->whenLoaded('server', fn () => $this->server->name),
            'serverSlug' => $this->whenLoaded('server', fn () => $this->server->slug),
            'filename' => $this->filename,
            'resourceType' => $this->resource_type,
            'resourceId' => $this->resource_id,
            'displayableName' => $this->displayable_name,
            'status' => $this->status,
            'exitCode' => $this->exit_code,
            'errorMessage' => $this->error_message,
            'output' => $this->output,
            'duration' => $this->duration,
            'executedAt' => $this->executed_at?->toISOString(),
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
