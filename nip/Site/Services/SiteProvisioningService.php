<?php

namespace Nip\Site\Services;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Nip\Site\Enums\SiteProvisioningStep;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Enums\SiteType;
use Nip\Site\Jobs\Provisioning\BuildFrontendAssetsJob;
use Nip\Site\Jobs\Provisioning\CloneRepositoryJob;
use Nip\Site\Jobs\Provisioning\ConfigureWwwRedirectJob;
use Nip\Site\Jobs\Provisioning\CreateEnvironmentFileJob;
use Nip\Site\Jobs\Provisioning\CreateLogrotateConfigJob;
use Nip\Site\Jobs\Provisioning\CreateNginxServerBlockJob;
use Nip\Site\Jobs\Provisioning\CreatePhpFpmPoolJob;
use Nip\Site\Jobs\Provisioning\CreateSiteConfigDirectoryJob;
use Nip\Site\Jobs\Provisioning\CreateSiteDirectoryJob;
use Nip\Site\Jobs\Provisioning\EnableNginxSiteJob;
use Nip\Site\Jobs\Provisioning\FinalizeSiteJob;
use Nip\Site\Jobs\Provisioning\InstallComposerDependenciesJob;
use Nip\Site\Jobs\Provisioning\InstallWordPressJob;
use Nip\Site\Jobs\Provisioning\InstallWpCliJob;
use Nip\Site\Jobs\Provisioning\RestartServicesJob;
use Nip\Site\Jobs\Provisioning\RunMigrationsJob;
use Nip\Site\Models\Site;

class SiteProvisioningService
{
    public function dispatch(Site $site): Batch
    {
        $site->update([
            'status' => SiteStatus::Installing,
            'provisioning_step' => SiteProvisioningStep::Initializing->value,
        ]);

        $jobs = $this->buildJobChain($site);

        $batch = Bus::batch([
            $jobs,
        ])
            ->name("Installing site: {$site->domain}")
            ->then(function (Batch $batch) use ($site) {
                $site->update([
                    'status' => SiteStatus::Installed,
                    'provisioning_step' => null,
                    'batch_id' => null,
                ]);
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($site) {
                $site->update([
                    'status' => SiteStatus::Failed,
                    'batch_id' => null,
                ]);
            })
            ->onQueue('provisioning')
            ->dispatch();

        $site->update([
            'batch_id' => $batch->id,
        ]);

        return $batch;
    }

    /**
     * @return array<int, object>
     */
    protected function buildJobChain(Site $site): array
    {
        $jobs = [];

        // System-level steps (run as root)
        // Step 1: Create site configuration directory
        $jobs[] = new CreateSiteConfigDirectoryJob($site);

        // Step 2: Create Nginx server block
        $jobs[] = new CreateNginxServerBlockJob($site);

        // Step 3: Configure www redirect
        $jobs[] = new ConfigureWwwRedirectJob($site);

        // Step 4: Enable Nginx site
        $jobs[] = new EnableNginxSiteJob($site);

        // Step 5: Create PHP-FPM pool (PHP-based sites only)
        if ($site->type->isPhpBased()) {
            $jobs[] = new CreatePhpFpmPoolJob($site);
        }

        // Step 6: Restart services
        $jobs[] = new RestartServicesJob($site);

        // Step 7: Create logrotate config
        $jobs[] = new CreateLogrotateConfigJob($site);

        // Step 8: Install WP-CLI (WordPress only)
        if ($site->type === SiteType::WordPress) {
            $jobs[] = new InstallWpCliJob($site);
        }

        // Application-level steps (run as site user)
        // Step 9: Create site directory
        $jobs[] = new CreateSiteDirectoryJob($site);

        // Step 10: Clone repository (conditional)
        $jobs[] = new CloneRepositoryJob($site);

        // Step 11: Create environment file (skipped for auto-install types)
        if (! $site->type->requiresAutoInstall()) {
            $jobs[] = new CreateEnvironmentFileJob($site);
        }

        // Step 12: Install Composer dependencies (conditional)
        if ($site->repository) {
            $jobs[] = new InstallComposerDependenciesJob($site);
        }

        // Step 13: Build frontend assets (conditional)
        if ($site->repository && $site->build_command) {
            $jobs[] = new BuildFrontendAssetsJob($site);
        }

        // Step 14: Run migrations (conditional)
        if ($site->repository && $site->type->hasMigrations()) {
            $jobs[] = new RunMigrationsJob($site);
        }

        // Step 15: Install application (WordPress/phpMyAdmin)
        if ($site->type->requiresAutoInstall()) {
            $jobs[] = new InstallWordPressJob($site);
        }

        // Step 16: Finalize site
        $jobs[] = new FinalizeSiteJob($site);

        return $jobs;
    }
}
