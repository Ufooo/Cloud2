<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class CloneRepositoryJob extends BaseSiteProvisionJob
{
    protected function getRunAsUser(): ?string
    {
        return $this->site->user;
    }

    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::CloningRepository;
    }

    protected function generateScript(): string
    {
        $cloneUrl = $this->site->getCloneUrl();

        return view('provisioning.scripts.site.steps.clone-repository', [
            'site' => $this->site,
            'skipClone' => ! $this->site->repository,
            'repository' => $cloneUrl,
            'branch' => $this->site->branch ?? 'main',
            'siteRoot' => $this->site->getSiteRoot(),
            'rootDirectory' => $this->site->root_directory,
            'user' => $this->site->user,
        ])->render();
    }
}
