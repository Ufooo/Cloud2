<?php

namespace Nip\Domain\Jobs;

use Nip\Domain\Jobs\Concerns\HandlesCertificateProvision;
use Nip\Domain\Models\Certificate;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Services\SSH\ExecutionResult;

class EnableSslJob extends BaseProvisionJob
{
    use HandlesCertificateProvision;

    public function __construct(
        public Certificate $certificate,
    ) {}

    protected function generateScript(): string
    {
        return view('provisioning.scripts.certificate.enable-ssl', $this->getCertificateViewData())->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->certificate->update([
            'active' => true,
        ]);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->certificate->update([
            'active' => false,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [...$this->getBaseTags(), 'enable-ssl'];
    }
}
