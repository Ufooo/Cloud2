<?php

namespace Nip\Site\Jobs;

use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Models\Site;

class InstallSiteJob extends BaseProvisionJob
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
        $nginxConfig = view('provisioning.scripts.site.partials.nginx-config', [
            'site' => $this->site,
            'user' => $this->site->user,
            'domain' => $this->site->domain,
            'fullPath' => $this->site->getFullPath(),
            'rootPath' => $this->site->getRootPath(),
            'phpSocket' => $this->site->getPhpSocketPath(),
            'phpVersion' => $this->site->getEffectivePhpVersion(),
            'siteType' => $this->site->type,
            'allowWildcard' => $this->site->allow_wildcard,
            'wwwRedirectType' => $this->site->www_redirect_type,
        ])->render();

        return view('provisioning.scripts.site.install', [
            'site' => $this->site,
            'user' => $this->site->user,
            'domain' => $this->site->domain,
            'fullPath' => $this->site->getFullPath(),
            'webPath' => $this->site->getWebPath(),
            'webDirectory' => $this->site->web_directory,
            'phpVersion' => $this->site->getEffectivePhpVersion(),
            'siteType' => $this->site->type,
            'allowWildcard' => $this->site->allow_wildcard,
            'wwwRedirectType' => $this->site->www_redirect_type,
            'nginxConfig' => $nginxConfig,
            'installedPhpVersions' => $this->site->server->phpVersions->pluck('version')->toArray(),
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->site->update([
            'status' => SiteStatus::Installed,
        ]);

        // Enable all domain records
        $this->site->domainRecords()->update([
            'status' => DomainRecordStatus::Enabled,
        ]);

        // Auto-deploy if repository is configured
        if ($this->site->repository) {
            $this->site->update(['deploy_status' => DeployStatus::Deploying]);
            DeploySiteJob::dispatch($this->site);
        }
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->site->update([
            'status' => SiteStatus::Failed,
        ]);
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
            'install',
        ];
    }
}
