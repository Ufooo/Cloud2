<?php

namespace Nip\BackgroundProcess\Jobs;

use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Events\SiteResourceStatusUpdated;

class RestartBackgroundProcessJob extends BaseProvisionJob
{
    public int $tries = 1;

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
        return view('provisioning.scripts.background-process.restart', [
            'programName' => 'netipar-'.$this->process->id,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->dispatchStatusUpdate('restarted');
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->dispatchStatusUpdate('restart_failed');
    }

    protected function dispatchStatusUpdate(string $status): void
    {
        if ($this->process->site_id) {
            SiteResourceStatusUpdated::dispatch(
                $this->process->site,
                'background_process',
                $this->process->id,
                $status
            );
        } else {
            ServerResourceStatusUpdated::dispatch(
                $this->process->server,
                'background_process',
                $this->process->id,
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
            'background_process_restart',
            'background_process:'.$this->process->id,
            'server:'.$this->process->server_id,
        ];
    }
}
