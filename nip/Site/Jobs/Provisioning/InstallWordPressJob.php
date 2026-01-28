<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class InstallWordPressJob extends BaseSiteProvisionJob
{
    protected function getRunAsUser(): ?string
    {
        return $this->site->user;
    }

    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::InstallingApplication;
    }

    protected function generateScript(): string
    {
        $this->site->load(['database', 'databaseUser']);

        return view('provisioning.scripts.site.steps.install-application', [
            'site' => $this->site,
            'siteRoot' => $this->site->getSiteRoot(),
            'applicationPath' => $this->site->getApplicationPath(),
            'webDirectory' => $this->site->web_directory,
            'phpVersion' => $this->site->php_version?->version(),
            'user' => $this->site->user,
        ])->render();
    }
}
