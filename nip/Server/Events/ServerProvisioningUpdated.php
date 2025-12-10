<?php

namespace Nip\Server\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nip\Server\Models\Server;

class ServerProvisioningUpdated implements ShouldBroadcast
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
