<?php

namespace Nip\Site\Jobs\Provisioning;

use Illuminate\Bus\Batchable;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\SiteProvisioningStep;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Events\SiteProvisioningStepChanged;
use Nip\Site\Models\Site;

abstract class BaseSiteProvisionJob extends BaseProvisionJob
{
    use Batchable;

    public function __construct(
        public Site $site,
    ) {}

    abstract protected function getStep(): SiteProvisioningStep;

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

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->site->update([
            'provisioning_step' => $this->getStep()->value,
        ]);

        SiteProvisioningStepChanged::dispatch($this->site->fresh());
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
            'step:'.$this->getStep()->name,
        ];
    }
}
