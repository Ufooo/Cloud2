<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class ConfigureWwwRedirectJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::ConfiguringWwwRedirect;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.create-www-redirect', [
            'site' => $this->site,
            'domain' => $this->site->domain,
        ])->render();
    }
}
