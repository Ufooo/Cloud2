<?php

namespace Nip\Site\Jobs;

use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Models\Site;

class CreateSiteDirectoryJob extends BaseProvisionJob
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

    protected function getRunAsUser(): ?string
    {
        return $this->site->user;
    }

    protected function generateScript(): string
    {
        $defaultIndexScript = view('provisioning.scripts.site.partials.default-index-html', [
            'webDirectory' => $this->site->web_directory,
            'domain' => $this->site->domain,
        ])->render();

        return view('provisioning.scripts.site.steps.create-site-directory', [
            'site' => $this->site,
            'user' => $this->site->user,
            'domain' => $this->site->domain,
            'fullPath' => $this->site->getFullPath(),
            'webDirectory' => $this->site->web_directory,
            'siteType' => $this->site->type,
            'defaultIndexScript' => $defaultIndexScript,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        // Directory created, continue with InstallSiteJob
        InstallSiteJob::dispatch($this->site);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->site->update([
            'status' => \Nip\Site\Enums\SiteStatus::Failed,
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
            'create-directory',
        ];
    }
}
