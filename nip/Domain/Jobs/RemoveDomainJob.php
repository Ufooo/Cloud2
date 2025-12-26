<?php

namespace Nip\Domain\Jobs;

use Nip\Domain\Models\DomainRecord;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class RemoveDomainJob extends BaseProvisionJob
{
    private int $siteId;

    private int $serverId;

    private string $domainName;

    public function __construct(
        public DomainRecord $domainRecord,
    ) {
        $this->siteId = $domainRecord->site_id;
        $this->serverId = $domainRecord->site->server_id;
        $this->domainName = $domainRecord->name;
    }

    protected function getResourceType(): string
    {
        return 'domain';
    }

    protected function getResourceId(): ?int
    {
        return $this->domainRecord->id;
    }

    protected function getServer(): Server
    {
        return $this->domainRecord->site->server;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.domain.remove', [
            'site' => $this->domainRecord->site,
            'domain' => $this->domainRecord->name,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->domainRecord->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Keep the domain record with Removing status for manual intervention
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'provision',
            'domain',
            'domain:'.$this->domainRecord->id,
            'site:'.$this->siteId,
            'server:'.$this->serverId,
            'remove',
        ];
    }
}
