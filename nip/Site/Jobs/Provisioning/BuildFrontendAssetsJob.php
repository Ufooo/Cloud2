<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class BuildFrontendAssetsJob extends BaseSiteProvisionJob
{
    protected function getRunAsUser(): ?string
    {
        return $this->site->user;
    }

    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::BuildingFrontendAssets;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.build-frontend-assets', [
            'site' => $this->site,
            'applicationPath' => $this->site->getApplicationPath(),
            'user' => $this->site->user,
            'buildCommand' => $this->site->build_command,
            'packageManager' => $this->site->package_manager,
            'hasRepository' => ! empty($this->site->repository),
        ])->render();
    }
}
