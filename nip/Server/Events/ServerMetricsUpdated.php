<?php

namespace Nip\Server\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nip\Server\Models\Server;

class ServerMetricsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Server $server
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dashboard'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'serverId' => $this->server->id,
            'status' => $this->server->status->value,
            'statusLabel' => $this->server->status->label(),
            'isConnected' => $this->server->status->isConnected(),
            'uptimeFormatted' => $this->server->uptime_formatted,
            'loadAvgFormatted' => $this->server->load_avg_formatted,
            'cpuPercent' => $this->server->cpu_percent ?? 0,
            'ramTotalBytes' => $this->server->ram_total_bytes,
            'ramUsedBytes' => $this->server->ram_used_bytes,
            'ramPercent' => $this->server->ram_percent ?? 0,
            'diskTotalBytes' => $this->server->disk_total_bytes,
            'diskUsedBytes' => $this->server->disk_used_bytes,
            'diskPercent' => $this->server->disk_percent ?? 0,
            'lastMetricsAt' => $this->server->last_metrics_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ServerMetricsUpdated';
    }
}
