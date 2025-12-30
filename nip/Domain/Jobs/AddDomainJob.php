<?php

namespace Nip\Domain\Jobs;

use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Models\DomainRecord;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class AddDomainJob extends BaseProvisionJob
{
    public function __construct(
        public DomainRecord $domainRecord,
    ) {}

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
        $site = $this->domainRecord->site;

        $nginxConfig = $this->generateNginxConfig($site);
        $wwwRedirectConfig = $this->generateWwwRedirectConfig();

        return view('provisioning.scripts.domain.add', [
            'site' => $site,
            'domainRecord' => $this->domainRecord,
            'domain' => $this->domainRecord->name,
            'nginxConfig' => $nginxConfig,
            'wwwRedirectConfig' => $wwwRedirectConfig,
        ])->render();
    }

    protected function generateNginxConfig(\Nip\Site\Models\Site $site): string
    {
        return view('provisioning.scripts.domain.partials.nginx-config', [
            'site' => $site,
            'domain' => $this->domainRecord->name,
            'fullPath' => $site->getFullPath(),
            'rootPath' => $site->getRootPath(),
            'phpSocket' => $site->getPhpSocketPath(),
            'siteType' => $site->type,
            'allowWildcard' => $this->domainRecord->allow_wildcard,
            'wwwRedirectType' => $this->domainRecord->www_redirect_type,
        ])->render();
    }

    protected function generateWwwRedirectConfig(): string
    {
        return view('provisioning.scripts.partials.nginx-www-redirect', [
            'domain' => $this->domainRecord->name,
            'wwwRedirectType' => $this->domainRecord->www_redirect_type,
            'allowWildcard' => $this->domainRecord->allow_wildcard,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->domainRecord->update([
            'status' => DomainRecordStatus::Enabled,
        ]);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->domainRecord->update([
            'status' => DomainRecordStatus::Failed,
        ]);
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
            'site:'.$this->domainRecord->site_id,
            'server:'.$this->domainRecord->site->server_id,
            'add',
        ];
    }
}
