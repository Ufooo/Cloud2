<?php

namespace Nip\Server\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nip\Server\Models\Server;

class ServerResourceStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Server $server,
        public string $resourceType,
        public ?int $resourceId = null,
        public ?string $status = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("servers.{$this->server->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'resourceType' => $this->resourceType,
            'resourceId' => $this->resourceId,
            'status' => $this->status,
        ];
    }

    public function broadcastAs(): string
    {
        return 'ServerResourceStatusUpdated';
    }
}
