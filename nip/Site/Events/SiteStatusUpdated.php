<?php

namespace Nip\Site\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nip\Site\Models\Site;

class SiteStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Site $site,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sites.{$this->site->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->site->status?->value,
            'deployStatus' => $this->site->deploy_status?->value,
        ];
    }

    public function broadcastAs(): string
    {
        return 'SiteStatusUpdated';
    }
}
