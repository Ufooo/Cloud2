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
        $scriptView = $this->certificate->verification_method === 'dns'
            ? 'provisioning.scripts.certificate.obtain-letsencrypt-dns01'
            : 'provisioning.scripts.certificate.obtain-letsencrypt';

        return view($scriptView, $this->getCertificateViewData())->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        // Verify certificate was actually deployed by checking output
        if (! $this->verifyCertificateDeployed($result->output)) {
            throw new \Exception('Certificate deployment could not be verified from script output. Certificate files may not have been created.');
        }

        $expiresAt = $this->parseCertificateExpiry($result->output);

        $this->certificate->update([
            'status' => CertificateStatus::Installed,
            'path' => $this->certificate->getCertPath(),
            'issued_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        // Automatically activate SSL after successful certificate obtainment
        EnableSslJob::dispatch($this->certificate);
    }

    protected function verifyCertificateDeployed(?string $output): bool
    {
        if (! $output) {
            return false;
        }

        // Check for success indicators in the script output
        // These are specific strings that only appear when certificate is actually deployed
        $successIndicators = [
            'Certificate deployed to',           // From deploy_cert hook
            'obtained successfully!',            // Final success message
            'CERT_EXPIRES:',                      // Certificate expiry output
        ];

        foreach ($successIndicators as $indicator) {
            if (str_contains($output, $indicator)) {
                return true;
            }
        }

        return false;
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
