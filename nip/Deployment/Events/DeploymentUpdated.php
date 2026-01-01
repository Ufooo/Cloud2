<?php

namespace Nip\Deployment\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nip\Deployment\Models\Deployment;

class DeploymentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Deployment $deployment,
    ) {}

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("deployments.{$this->deployment->id}"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->deployment->id,
            'status' => $this->deployment->status?->value,
            'hasOutput' => ! empty($this->deployment->output),
            'endedAt' => $this->deployment->ended_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DeploymentUpdated';
    }
}
