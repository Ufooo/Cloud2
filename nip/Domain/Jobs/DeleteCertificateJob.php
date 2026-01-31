<?php

namespace Nip\Domain\Jobs;

use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Jobs\Concerns\HandlesCertificateProvision;
use Nip\Domain\Models\Certificate;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Server\Services\SSH\SSHService;

class DeleteCertificateJob extends BaseProvisionJob
{
    use HandlesCertificateProvision;

    public function __construct(
        public Certificate $certificate,
    ) {}

    public function handle(SSHService $ssh): void
    {
        if ($this->shouldSkipFileDeletion()) {
            $this->certificate->delete();

            return;
        }

        parent::handle($ssh);
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.certificate.delete', $this->getCertificateViewData())->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->certificate->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->certificate->update([
            'status' => CertificateStatus::Installed,
        ]);
    }

    /**
     * Clone certificates share files with their source - never delete the files.
     * Source certificates that still have clones must also preserve files.
     */
    private function shouldSkipFileDeletion(): bool
    {
        if ($this->certificate->type === CertificateType::Clone) {
            return true;
        }

        $certPath = $this->certificate->getCertPath();

        return Certificate::query()
            ->where('id', '!=', $this->certificate->id)
            ->where('path', $certPath)
            ->exists();
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [...$this->getBaseTags(), 'delete'];
    }
}
