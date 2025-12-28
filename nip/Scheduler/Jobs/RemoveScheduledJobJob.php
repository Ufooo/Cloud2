<?php

namespace Nip\Scheduler\Jobs;

use Nip\Scheduler\Enums\JobStatus;
use Nip\Scheduler\Models\ScheduledJob;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Events\SiteResourceStatusUpdated;

class RemoveScheduledJobJob extends BaseProvisionJob
{
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public ScheduledJob $scheduledJob
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'scheduled_job';
    }

    protected function getResourceId(): ?int
    {
        return null;
    }

    protected function getServer(): Server
    {
        return $this->scheduledJob->server;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.scheduler.remove', [
            'user' => $this->scheduledJob->user,
            'jobId' => $this->scheduledJob->id,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $server = $this->scheduledJob->server;
        $site = $this->scheduledJob->site;
        $siteId = $this->scheduledJob->site_id;
        $jobId = $this->scheduledJob->id;

        $this->scheduledJob->delete();

        if ($siteId && $site) {
            SiteResourceStatusUpdated::dispatch(
                $site,
                'scheduled_job',
                $jobId,
                'deleted'
            );
        } else {
            ServerResourceStatusUpdated::dispatch(
                $server,
                'scheduled_job',
                $jobId,
                'deleted'
            );
        }
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->scheduledJob->update([
            'status' => JobStatus::Installed,
        ]);

        if ($this->scheduledJob->site_id) {
            SiteResourceStatusUpdated::dispatch(
                $this->scheduledJob->site,
                'scheduled_job',
                $this->scheduledJob->id,
                JobStatus::Installed->value
            );
        } else {
            ServerResourceStatusUpdated::dispatch(
                $this->scheduledJob->server,
                'scheduled_job',
                $this->scheduledJob->id,
                JobStatus::Installed->value
            );
        }
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'scheduled_job_remove',
            'scheduled_job:'.$this->scheduledJob->id,
            'server:'.$this->scheduledJob->server_id,
        ];
    }
}
