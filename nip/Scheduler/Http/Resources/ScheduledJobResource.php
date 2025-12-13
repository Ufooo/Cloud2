<?php

namespace Nip\Scheduler\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Scheduler\Enums\CronFrequency;
use Nip\Scheduler\Enums\JobStatus;
use Nip\Scheduler\Models\ScheduledJob;

/**
 * @mixin ScheduledJob
 */
class ScheduledJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->server);
        $isInstalled = $this->status === JobStatus::Installed;
        $isPaused = $this->status === JobStatus::Paused;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'command' => $this->command,
            'user' => $this->user,
            'frequency' => $this->frequency?->value,
            'displayableFrequency' => $this->frequency?->label(),
            'cron' => $this->cron,
            'effectiveCron' => $this->getEffectiveCron(),
            'heartbeatEnabled' => $this->heartbeat_enabled,
            'heartbeatUrl' => $this->heartbeat_url,
            'gracePeriod' => $this->grace_period?->value,
            'displayableGracePeriod' => $this->grace_period?->label(),
            'status' => $this->status?->value,
            'displayableStatus' => $this->status?->label(),
            'statusBadgeVariant' => $this->status?->badgeVariant(),
            'isCustomFrequency' => $this->frequency === CronFrequency::Custom,
            'createdAt' => $this->created_at?->toISOString(),
            'siteId' => $this->site_id,
            'siteDomain' => $this->whenLoaded('site', fn () => $this->site?->domain),
            'siteSlug' => $this->whenLoaded('site', fn () => $this->site?->slug),
            'can' => [
                'update' => $canUpdate && ($isInstalled || $isPaused),
                'delete' => $canUpdate && $this->status !== JobStatus::Installing,
                'pause' => $canUpdate && $isInstalled,
                'resume' => $canUpdate && $isPaused,
            ],
        ];
    }
}
