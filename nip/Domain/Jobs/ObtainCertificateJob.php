<?php

namespace Nip\Domain\Jobs;

use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Jobs\Concerns\HandlesCertificateProvision;
use Nip\Domain\Models\Certificate;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Services\SSH\ExecutionResult;

class ObtainCertificateJob extends BaseProvisionJob
{
    use HandlesCertificateProvision;

    public function __construct(
        public Certificate $certificate,
    ) {}

    protected function generateScript(): string
    {
        return view('provisioning.scripts.certificate.obtain-letsencrypt', $this->getCertificateViewData())->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $expiresAt = $this->parseCertificateExpiry($result->output);

        $this->certificate->update([
            'status' => CertificateStatus::Installed,
            'path' => $this->certificate->getCertPath(),
            'issued_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        // Automatically activate SSL after successful certificate obtainment
        // Deactivate any other certificates for this site first
        $this->certificate->site->certificates()
            ->whereNot('id', $this->certificate->id)
            ->update(['active' => false]);

        EnableSslJob::dispatch($this->certificate);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->certificate->update([
            'status' => CertificateStatus::Failed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [...$this->getBaseTags(), 'obtain'];
    }
}
