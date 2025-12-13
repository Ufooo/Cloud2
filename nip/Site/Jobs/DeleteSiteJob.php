<?php

namespace Nip\Site\Jobs;

use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Models\Site;

class DeleteSiteJob extends BaseProvisionJob
{
    public function __construct(
        public Site $site,
    ) {}

    protected function getResourceType(): string
    {
        return 'site';
    }

    protected function getResourceId(): ?int
    {
        return $this->site->id;
    }

    protected function getServer(): Server
    {
        return $this->site->server;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.delete', [
            'site' => $this->site,
            'user' => $this->site->user,
            'domain' => $this->site->domain,
            'fullPath' => $this->site->getFullPath(),
            'phpVersion' => $this->site->php_version ?? $this->site->server->php_version,
            'isIsolated' => $this->site->is_isolated,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->site->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Keep the site in deleting status for manual intervention
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'provision',
            'site',
            'site:'.$this->site->id,
            'server:'.$this->site->server_id,
            'delete',
        ];
    }
}
