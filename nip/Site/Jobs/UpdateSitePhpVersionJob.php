<?php

namespace Nip\Site\Jobs;

use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Models\Site;

class UpdateSitePhpVersionJob extends BaseProvisionJob
{
    public int $timeout = 120;

    public function __construct(
        public Site $site,
        public string $newVersion
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'site_php_version';
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
        return view('provisioning.scripts.site.steps.update-php-version', [
            'site' => $this->site,
            'domain' => $this->site->domain,
            'oldVersion' => $this->site->getEffectivePhpVersion(),
            'newVersion' => $this->newVersion,
            'user' => $this->site->user,
            'fullPath' => $this->site->getFullPath(),
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->site->update([
            'php_version' => $this->newVersion,
        ]);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Log the failure, version remains unchanged
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'site_php_version',
            'site:'.$this->site->id,
            'server:'.$this->site->server_id,
        ];
    }
}
