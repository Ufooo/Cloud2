<?php

namespace Nip\Server\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nip\Server\Models\Server;

class ServerProvisioningUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Server $server,
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
            'provisionStep' => $this->server->provision_step,
        ];
    }
}
