<?php

namespace Nip\Domain\Console\Commands;

use Illuminate\Console\Command;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Jobs\RenewCertificateJob;
use Nip\Domain\Models\Certificate;

class RenewExpiringCertificatesCommand extends Command
{
    protected $signature = 'certificates:renew-expiring {--days=5 : Days before expiry to trigger renewal}';

    protected $description = 'Renew SSL certificates that are expiring soon';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $expiryThreshold = now()->addDays($days);

        $certificates = Certificate::query()
            ->where('type', CertificateType::LetsEncrypt)
            ->where('status', CertificateStatus::Installed)
            ->where('active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $expiryThreshold)
            ->where('expires_at', '>', now())
            ->get();

        if ($certificates->isEmpty()) {
            $this->info('No certificates need renewal.');

            return self::SUCCESS;
        }

        $this->info("Found {$certificates->count()} certificate(s) expiring within {$days} days.");

        foreach ($certificates as $certificate) {
            $daysUntilExpiry = (int) now()->diffInDays($certificate->expires_at);

            $this->line("  - Certificate #{$certificate->id} for {$certificate->site->domain} (expires in {$daysUntilExpiry} days)");

            $certificate->update(['status' => CertificateStatus::Renewing]);

            RenewCertificateJob::dispatch($certificate);
        }

        $this->info("Dispatched {$certificates->count()} renewal job(s).");

        return self::SUCCESS;
    }
}
