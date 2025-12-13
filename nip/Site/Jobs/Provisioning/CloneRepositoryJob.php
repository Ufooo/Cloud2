<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class CloneRepositoryJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::CloningRepository;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.clone-repository', [
            'site' => $this->site,
            'skipClone' => ! $this->site->repository,
            'repository' => $this->site->repository,
            'branch' => $this->site->branch ?? 'main',
            'fullPath' => $this->site->getFullPath(),
            'user' => $this->site->user,
            'deployKey' => $this->site->deploy_key,
        ])->render();
    }
}
