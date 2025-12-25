<?php

namespace Nip\Scheduler\Jobs;

use Nip\Scheduler\Enums\JobStatus;
use Nip\Scheduler\Models\ScheduledJob;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class RemoveScheduledJobJob extends BaseProvisionJob
{
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
        $this->scheduledJob->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->scheduledJob->update([
            'status' => JobStatus::Installed,
        ]);
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
