<?php

namespace Nip\Domain\Jobs;

use Illuminate\Queue\Middleware\WithoutOverlapping;
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
    ) {
        $this->queue = 'provisioning';
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('certificate-renewal'))
                ->releaseAfter(600)
                ->expireAfter(3600),
        ];
    }

    protected function generateScript(): string
    {
        $usesDns = $this->certificate->verification_method === 'dns'
            || $this->certificate->isWildcard();

        $scriptView = $usesDns
            ? 'provisioning.scripts.certificate.renew-letsencrypt-dns01'
            : 'provisioning.scripts.certificate.renew-letsencrypt';

        return view($scriptView, $this->getCertificateViewData())->render();
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
