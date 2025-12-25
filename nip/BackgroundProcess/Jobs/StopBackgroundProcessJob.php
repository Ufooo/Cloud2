<?php

namespace Nip\BackgroundProcess\Jobs;

use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class StopBackgroundProcessJob extends BaseProvisionJob
{
    public int $timeout = 60;

    public function __construct(
        public BackgroundProcess $process
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'background_process';
    }

    protected function getResourceId(): ?int
    {
        return null;
    }

    protected function getServer(): Server
    {
        return $this->process->server;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.background-process.stop', [
            'programName' => 'netipar-'.$this->process->id,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        // Process stopped successfully, no status change needed
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Log the failure but don't change process status
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'background_process_stop',
            'background_process:'.$this->process->id,
            'server:'.$this->process->server_id,
        ];
    }
}
