<?php

namespace Nip\Domain\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
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

        $jobs = [];

        foreach ($certificates as $certificate) {
            $daysUntilExpiry = (int) now()->diffInDays($certificate->expires_at);

            $this->line("  - Certificate #{$certificate->id} for {$certificate->site->domain} (expires in {$daysUntilExpiry} days)");

            $certificate->update(['status' => CertificateStatus::Renewing]);

            $jobs[] = new RenewCertificateJob($certificate);
        }

        Bus::chain($jobs)
            ->onQueue('provisioning')
            ->catch(function (\Throwable $e) {
                // Reset any remaining "renewing" certificates back to installed
                // so they can be retried on the next scheduled run
                Certificate::query()
                    ->where('status', CertificateStatus::Renewing)
                    ->update(['status' => CertificateStatus::Installed->value]);
            })
            ->dispatch();

        $this->info('Renewal chain dispatched.');

        return self::SUCCESS;
    }
}
