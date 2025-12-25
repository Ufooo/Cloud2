<?php

namespace Nip\BackgroundProcess\Jobs;

use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class RemoveBackgroundProcessJob extends BaseProvisionJob
{
    public int $timeout = 120;

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
        return view('provisioning.scripts.background-process.remove', [
            'processId' => $this->process->id,
            'programName' => 'netipar-'.$this->process->id,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->process->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->process->update([
            'status' => ProcessStatus::Installed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'background_process_remove',
            'background_process:'.$this->process->id,
            'server:'.$this->process->server_id,
        ];
    }
}
