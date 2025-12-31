<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class EnableNginxSiteJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::EnablingNginxSite;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.enable-nginx-site', [
            'site' => $this->site,
            'domain' => $this->site->domain,
        ])->render();
    }
}
