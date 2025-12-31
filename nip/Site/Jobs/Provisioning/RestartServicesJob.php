<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class RestartServicesJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::RestartingServices;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.reload-services', [
            'site' => $this->site,
            'domain' => $this->site->domain,
            'installedPhpVersions' => $this->site->server->phpVersions->pluck('version')->toArray(),
        ])->render();
    }
}
