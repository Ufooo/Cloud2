<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class RunMigrationsJob extends BaseSiteProvisionJob
{
    protected function getRunAsUser(): ?string
    {
        return $this->site->user;
    }

    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::RunningMigrations;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.run-migrations', [
            'site' => $this->site,
            'applicationPath' => $this->site->getApplicationPath(),
            'user' => $this->site->user,
            'siteType' => $this->site->type,
            'hasRepository' => ! empty($this->site->repository),
        ])->render();
    }
}
