<?php

namespace Nip\Domain\Jobs;

use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Jobs\Concerns\HandlesCertificateProvision;
use Nip\Domain\Models\Certificate;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Services\SSH\ExecutionResult;

class UpdateCertificateJob extends BaseProvisionJob
{
    use HandlesCertificateProvision;

    public function __construct(
        public Certificate $certificate,
    ) {
        $this->queue = 'provisioning';
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.certificate.update-existing', [
            ...$this->getCertificateViewData(),
            'certificateContent' => $this->certificate->certificate,
            'privateKeyContent' => $this->certificate->private_key,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $expiresAt = $this->parseCertificateExpiry($result->output);

        $this->certificate->update([
            'status' => CertificateStatus::Installed,
            'issued_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        // If certificate is active, link matching domain records
        if ($this->certificate->active) {
            $this->linkDomainRecords();
        }
    }

    /**
     * Link domain records to this certificate if it covers them (including wildcard matching).
     */
    protected function linkDomainRecords(): void
    {
        foreach ($this->certificate->site->domainRecords as $domainRecord) {
            // Skip if already has a certificate
            if ($domainRecord->certificate_id) {
                continue;
            }

            // Link if this certificate covers the domain (exact or wildcard match)
            if ($this->certificate->coversDomain($domainRecord->name)) {
                $domainRecord->update(['certificate_id' => $this->certificate->id]);
            }
        }
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
        return [...$this->getBaseTags(), 'update-existing'];
    }
}
