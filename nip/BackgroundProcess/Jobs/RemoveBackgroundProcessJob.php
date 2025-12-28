<?php

namespace Nip\BackgroundProcess\Jobs;

use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Events\SiteResourceStatusUpdated;

class RemoveBackgroundProcessJob extends BaseProvisionJob
{
    public int $tries = 1;

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
        $server = $this->process->server;
        $site = $this->process->site;
        $siteId = $this->process->site_id;
        $processId = $this->process->id;

        $this->process->delete();

        if ($siteId && $site) {
            SiteResourceStatusUpdated::dispatch(
                $site,
                'background_process',
                $processId,
                'deleted'
            );
        } else {
            ServerResourceStatusUpdated::dispatch(
                $server,
                'background_process',
                $processId,
                'deleted'
            );
        }
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->process->update([
            'status' => ProcessStatus::Installed,
        ]);

        if ($this->process->site_id) {
            SiteResourceStatusUpdated::dispatch(
                $this->process->site,
                'background_process',
                $this->process->id,
                ProcessStatus::Installed->value
            );
        } else {
            ServerResourceStatusUpdated::dispatch(
                $this->process->server,
                'background_process',
                $this->process->id,
                ProcessStatus::Installed->value
            );
        }
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
