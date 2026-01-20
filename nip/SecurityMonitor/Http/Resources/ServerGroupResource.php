<?php

namespace Nip\SecurityMonitor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'server' => [
                'id' => $this->id,
                'name' => $this->name,
                'slug' => $this->slug,
                'ipAddress' => $this->ip_address,
            ],
            'sites' => SecuritySiteResource::collection($this->sites),
        ];
    }
}
