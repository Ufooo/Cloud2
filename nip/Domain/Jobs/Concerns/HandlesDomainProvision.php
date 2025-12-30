<?php

namespace Nip\Domain\Jobs\Concerns;

use Nip\Server\Models\Server;

trait HandlesDomainProvision
{
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

    /**
     * @return array<string>
     */
    protected function getBaseTags(): array
    {
        return [
            'provision',
            'domain',
            'domain:'.$this->domainRecord->id,
            'site:'.$this->domainRecord->site_id,
            'server:'.$this->domainRecord->site->server_id,
        ];
    }
}
