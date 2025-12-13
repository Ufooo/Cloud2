<?php

namespace Nip\Database\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Database\Models\Database;

/**
 * @mixin Database
 */
class DatabaseResource extends JsonResource
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
            'serverSlug' => $this->whenLoaded('server', fn () => $this->server->slug),
            'siteId' => $this->site_id,
            'siteDomain' => $this->whenLoaded('site', fn () => $this->site?->domain),
            'siteSlug' => $this->whenLoaded('site', fn () => $this->site?->slug),
            'name' => $this->name,
            'size' => $this->size,
            'displayableSize' => $this->getDisplayableSize(),
            'createdAt' => $this->created_at?->toISOString(),
            'createdAtHuman' => $this->created_at?->diffForHumans(),
        ];
    }

    private function getDisplayableSize(): ?string
    {
        if ($this->size === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }
}
