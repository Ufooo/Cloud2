<?php

namespace Nip\Site\Services;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Nip\Site\Enums\SiteProvisioningStep;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Jobs\Provisioning\BuildFrontendAssetsJob;
use Nip\Site\Jobs\Provisioning\CloneRepositoryJob;
use Nip\Site\Jobs\Provisioning\ConfigureNginxJob;
use Nip\Site\Jobs\Provisioning\CreateEnvironmentFileJob;
use Nip\Site\Jobs\Provisioning\FinalizeSiteJob;
use Nip\Site\Jobs\Provisioning\InstallComposerDependenciesJob;
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

        $batch = Bus::batch($jobs)
            ->name("Site Provisioning: {$site->domain}")
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
            ->finally(function (Batch $batch) {
                // Cleanup or logging
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

        // Step 1: Configure Nginx (always required)
        $jobs[] = new ConfigureNginxJob($site);

        // Step 2: Clone Repository (conditional)
        $jobs[] = new CloneRepositoryJob($site);

        // Step 3: Create Environment File (conditional)
        $jobs[] = new CreateEnvironmentFileJob($site);

        // Step 4: Install Dependencies (conditional on repository)
        if ($site->repository) {
            $jobs[] = new InstallComposerDependenciesJob($site);
        }

        // Step 5: Build Frontend Assets (conditional on build command)
        if ($site->repository && $site->build_command) {
            $jobs[] = new BuildFrontendAssetsJob($site);
        }

        // Step 6: Run Migrations (conditional on site type and repository)
        if ($site->repository && $site->type->hasMigrations()) {
            $jobs[] = new RunMigrationsJob($site);
        }

        // Step 7: Finalize (always required)
        $jobs[] = new FinalizeSiteJob($site);

        return $jobs;
    }
}
