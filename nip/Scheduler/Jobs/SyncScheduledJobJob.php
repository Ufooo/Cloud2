<?php

namespace Nip\Scheduler\Jobs;

use Nip\Scheduler\Enums\JobStatus;
use Nip\Scheduler\Models\ScheduledJob;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Events\SiteResourceStatusUpdated;

class SyncScheduledJobJob extends BaseProvisionJob
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
        return view('provisioning.scripts.scheduler.sync', [
            'job' => $this->scheduledJob,
            'cronExpression' => $this->scheduledJob->getEffectiveCron(),
            'user' => $this->scheduledJob->user,
            'command' => $this->scheduledJob->command,
            'jobId' => $this->scheduledJob->id,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->scheduledJob->update([
            'status' => JobStatus::Installed,
        ]);

        // Update site packages if this is the Laravel Scheduler
        if ($this->scheduledJob->site_id && $this->scheduledJob->name === 'Laravel Scheduler') {
            $site = $this->scheduledJob->site;
            $packages = $site->packages ?? [];
            $packages['scheduler'] = true;
            $site->update(['packages' => $packages]);
        }

        $this->dispatchStatusUpdate(JobStatus::Installed->value);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->scheduledJob->update([
            'status' => JobStatus::Failed,
        ]);

        $this->dispatchStatusUpdate(JobStatus::Failed->value);
    }

    protected function dispatchStatusUpdate(string $status): void
    {
        if ($this->scheduledJob->site_id) {
            SiteResourceStatusUpdated::dispatch(
                $this->scheduledJob->site,
                'scheduled_job',
                $this->scheduledJob->id,
                $status
            );
        } else {
            ServerResourceStatusUpdated::dispatch(
                $this->scheduledJob->server,
                'scheduled_job',
                $this->scheduledJob->id,
                $status
            );
        }
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'scheduled_job_sync',
            'scheduled_job:'.$this->scheduledJob->id,
            'server:'.$this->scheduledJob->server_id,
        ];
    }
}
