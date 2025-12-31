<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class CreateLogrotateConfigJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::CreatingLogrotateConfig;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.create-logrotate', [
            'site' => $this->site,
            'domain' => $this->site->domain,
            'user' => $this->site->user,
            'fullPath' => $this->site->getFullPath(),
        ])->render();
    }
}
