<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class CreatePhpFpmPoolJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::CreatingPhpFpmPool;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.create-isolated-fpm', [
            'site' => $this->site,
            'domain' => $this->site->domain,
            'user' => $this->site->user,
            'fullPath' => $this->site->getFullPath(),
            'phpVersion' => $this->site->php_version,
        ])->render();
    }
}
