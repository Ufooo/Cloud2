<?php

namespace Nip\Site\Jobs;

use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Events\SiteStatusUpdated;
use Nip\Site\Models\Site;

class DeploySiteJob extends BaseProvisionJob
{
    public int $tries = 1;

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
        $deployScriptContent = view("provisioning.scripts.deploy.{$this->site->type->value}", [
            'site' => $this->site,
        ])->render();

        // Wrap the deploy script with environment setup and error handling
        return view('provisioning.scripts.site.deploy-wrapper', [
            'site' => $this->site,
            'user' => $this->site->user,
            'domain' => $this->site->domain,
            'fullPath' => $this->site->getFullPath(),
            'currentPath' => $this->site->getCurrentPath(),
            'branch' => $this->site->branch ?? 'main',
            'phpVersion' => $this->site->getEffectivePhpVersion(),
            'deployScriptContent' => $deployScriptContent,
            'database' => $this->site->database,
            'databaseUser' => $this->site->databaseUser,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->site->update([
            'deploy_status' => DeployStatus::Deployed,
            'last_deployed_at' => now(),
        ]);

        SiteStatusUpdated::dispatch($this->site);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->site->update([
            'deploy_status' => DeployStatus::Failed,
        ]);

        SiteStatusUpdated::dispatch($this->site);
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
            'deploy',
        ];
    }
}
