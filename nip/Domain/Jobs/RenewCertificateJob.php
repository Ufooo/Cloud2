<?php

namespace Nip\Domain\Jobs;

use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Jobs\Concerns\HandlesCertificateProvision;
use Nip\Domain\Models\Certificate;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Services\SSH\ExecutionResult;

class RenewCertificateJob extends BaseProvisionJob
{
    use HandlesCertificateProvision;

    public function __construct(
        public Certificate $certificate,
    ) {}

    protected function generateScript(): string
    {
        return view('provisioning.scripts.certificate.renew-letsencrypt', $this->getCertificateViewData())->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $expiresAt = $this->parseCertificateExpiry($result->output);

        $this->certificate->update([
            'status' => CertificateStatus::Installed,
            'issued_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // On renewal failure, keep the old status if it was installed
        // This allows manual intervention without breaking the site
        if ($this->certificate->status === CertificateStatus::Renewing) {
            $this->certificate->update([
                'status' => CertificateStatus::Installed,
            ]);
        }
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [...$this->getBaseTags(), 'renew'];
    }
}
