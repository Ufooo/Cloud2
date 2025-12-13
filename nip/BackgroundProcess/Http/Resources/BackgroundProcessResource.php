<?php

namespace Nip\BackgroundProcess\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Enums\SupervisorProcessStatus;
use Nip\BackgroundProcess\Models\BackgroundProcess;

/**
 * @mixin BackgroundProcess
 */
class BackgroundProcessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->server);
        $isInstalled = $this->status === ProcessStatus::Installed;
        $isRunning = $this->supervisor_process_status === SupervisorProcessStatus::Running;
        $isStopped = in_array($this->supervisor_process_status, [
            SupervisorProcessStatus::Stopped,
            SupervisorProcessStatus::Exited,
            null,
        ], true);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'command' => $this->command,
            'directory' => $this->directory,
            'user' => $this->user,
            'processes' => $this->processes,
            'startsecs' => $this->startsecs,
            'stopwaitsecs' => $this->stopwaitsecs,
            'stopsignal' => $this->stopsignal?->value,
            'status' => $this->status?->value,
            'displayableStatus' => $this->status?->label(),
            'statusBadgeVariant' => $this->status?->badgeVariant(),
            'supervisorProcessStatus' => $this->supervisor_process_status?->value,
            'displayableSupervisorProcessStatus' => $this->supervisor_process_status?->label(),
            'supervisorBadgeVariant' => $this->supervisor_process_status?->badgeVariant(),
            'createdAt' => $this->created_at?->toISOString(),
            'siteId' => $this->site_id,
            'siteDomain' => $this->whenLoaded('site', fn () => $this->site?->domain),
            'siteSlug' => $this->whenLoaded('site', fn () => $this->site?->slug),
            'can' => [
                'update' => $canUpdate && $isInstalled,
                'delete' => $canUpdate && $this->status !== ProcessStatus::Installing,
                'restart' => $canUpdate && $isInstalled,
                'start' => $canUpdate && $isInstalled && $isStopped,
                'stop' => $canUpdate && $isInstalled && $isRunning,
            ],
        ];
    }
}
