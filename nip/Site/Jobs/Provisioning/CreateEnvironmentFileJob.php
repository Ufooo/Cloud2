<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class CreateEnvironmentFileJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::CreatingEnvironmentFile;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.create-environment-file', [
            'site' => $this->site,
            'fullPath' => $this->site->getFullPath(),
            'user' => $this->site->user,
            'environment' => $this->site->environment ?? '',
        ])->render();
    }
}
