<?php

namespace Nip\Server\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Models\Server;

/**
 * @mixin Server
 */
class ServerWidgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'ipAddress' => $this->ip_address,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'isConnected' => $this->status === ServerStatus::Connected,
            'sitesCount' => $this->sites_count ?? $this->sites()->count(),
            'lastConnectedAt' => $this->last_connected_at?->toISOString(),
            'uptimeFormatted' => $this->uptime_formatted,
            'loadAvgFormatted' => $this->load_avg_formatted,
            'cpuPercent' => $this->cpu_percent ?? 0,
            'ramTotalBytes' => $this->ram_total_bytes,
            'ramUsedBytes' => $this->ram_used_bytes,
            'ramPercent' => $this->ram_percent ?? 0,
            'diskTotalBytes' => $this->disk_total_bytes,
            'diskUsedBytes' => $this->disk_used_bytes,
            'diskPercent' => $this->disk_percent ?? 0,
            'lastMetricsAt' => $this->last_metrics_at?->toISOString(),
        ];
    }
}
