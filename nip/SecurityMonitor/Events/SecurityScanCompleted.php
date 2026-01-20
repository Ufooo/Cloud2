<?php

namespace Nip\SecurityMonitor\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nip\SecurityMonitor\Models\SecurityScan;

class SecurityScanCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  SecurityScan  $scan  The completed security scan
     */
    public function __construct(
        public SecurityScan $scan,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sites.{$this->scan->site_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'scanId' => $this->scan->id,
            'status' => $this->scan->status->value,
            'gitNewCount' => $this->scan->git_new_count,
            'completedAt' => $this->scan->completed_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'SecurityScanCompleted';
    }
}
