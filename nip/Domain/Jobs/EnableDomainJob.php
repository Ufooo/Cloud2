<?php

namespace Nip\Domain\Jobs;

use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Jobs\Concerns\HandlesDomainProvision;
use Nip\Domain\Models\DomainRecord;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Services\SSH\ExecutionResult;

class EnableDomainJob extends BaseProvisionJob
{
    use HandlesDomainProvision;

    public function __construct(
        public DomainRecord $domainRecord,
    ) {}

    protected function generateScript(): string
    {
        return view('provisioning.scripts.domain.enable', [
            'site' => $this->domainRecord->site,
            'domain' => $this->domainRecord->name,
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
        return [...$this->getBaseTags(), 'enable'];
    }
}
