<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class CreateSiteConfigDirectoryJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::CreatingSiteConfigDirectory;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.create-site-config-directory', [
            'site' => $this->site,
            'domain' => $this->site->domain,
        ])->render();
    }
}
