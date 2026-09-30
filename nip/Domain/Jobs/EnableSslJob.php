<?php

namespace Nip\Domain\Jobs;

use Illuminate\Support\Facades\Bus;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Jobs\Concerns\HandlesCertificateProvision;
use Nip\Domain\Models\Certificate;
use Nip\Domain\Models\DomainRecord;
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

        $this->linkDomainRecords();

        $this->regenerateDomainConfigs();
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

    /**
     * Regenerate the nginx config of every domain record this certificate covers.
     *
     * The enable-ssl script only patches the listen and certificate lines of the existing server
     * block, so a www name stays in the SSL server_name and nothing serves its redirect. Going
     * through UpdateDomainJob hands it to the domain's own www redirect instead. This is
     * independent of linkDomainRecords(): a record that already carries an older certificate is
     * regenerated too. The jobs are chained so the nginx reloads of one site run one after another.
     *
     * A chain stops at the first job that fails, and the update script fails for a record whose
     * config is not on disk. So the order is explicit, never the database's: the primary domain
     * goes first (it is the record this regeneration exists for), and the rest follow in creation
     * order.
     *
     * Only records that already have a sites-available file are included: a pending, creating or
     * failed record has nothing to regenerate. A disabled one does - disabling only drops the
     * sites-enabled symlink, and re-enabling only puts it back, so a disabled record left out here
     * would come back later still carrying the www name in its SSL server_name.
     */
    protected function regenerateDomainConfigs(): void
    {
        $regeneratableStatuses = [DomainRecordStatus::Enabled, DomainRecordStatus::Disabled];

        $jobs = $this->certificate->site->domainRecords
            ->filter(fn (DomainRecord $domainRecord): bool => in_array($domainRecord->status, $regeneratableStatuses, true)
                && $this->certificate->coversDomain($domainRecord->name))
            ->sortBy([
                fn (DomainRecord $a, DomainRecord $b): int => $b->isPrimary() <=> $a->isPrimary(),
                ['id', 'asc'],
            ])
            ->map(fn (DomainRecord $domainRecord): UpdateDomainJob => new UpdateDomainJob($domainRecord))
            ->values()
            ->all();

        if ($jobs === []) {
            return;
        }

        Bus::chain($jobs)->dispatch();
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
