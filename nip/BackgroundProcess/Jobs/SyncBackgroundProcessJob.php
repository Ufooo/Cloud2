<?php

namespace Nip\BackgroundProcess\Jobs;

use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Events\SiteResourceStatusUpdated;

class SyncBackgroundProcessJob extends BaseProvisionJob
{
    public int $tries = 1;

    public int $timeout = 120;

    /**
     * A supervisor program is named after the process id, so a config already
     * sitting under that name was put there by something else. Overwriting it
     * kills a running daemon, so the script refuses on a first install - only
     * an update, where the platform already owns the program, may rewrite it.
     */
    public function __construct(
        public BackgroundProcess $process,
        public bool $isInitialInstall = false
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
        return view('provisioning.scripts.background-process.sync', [
            'process' => $this->process,
            'processId' => $this->process->id,
            'name' => $this->process->name,
            'command' => $this->process->command,
            'directory' => $this->process->directory,
            'user' => $this->process->user,
            'processes' => $this->process->processes,
            'startsecs' => $this->process->startsecs,
            'stopwaitsecs' => $this->process->stopwaitsecs,
            'stopsignal' => $this->process->stopsignal?->value ?? 'TERM',
            'isInitialInstall' => $this->isInitialInstall,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->process->update([
            'status' => ProcessStatus::Installed,
        ]);

        $this->dispatchStatusUpdate(ProcessStatus::Installed->value);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->process->update([
            'status' => ProcessStatus::Failed,
        ]);

        $this->dispatchStatusUpdate(ProcessStatus::Failed->value);
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
            'background_process_sync',
            'background_process:'.$this->process->id,
            'server:'.$this->process->server_id,
        ];
    }
}
