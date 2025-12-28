<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class InstallComposerDependenciesJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::InstallingDependencies;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.install-composer-dependencies', [
            'site' => $this->site,
            'fullPath' => $this->site->getFullPath(),
            'user' => $this->site->user,
            'packageManager' => $this->site->package_manager,
            'hasRepository' => ! empty($this->site->repository),
            'composerPhpVersion' => $this->site->getEffectivePhpVersion(),
            'composerBinary' => '/usr/local/bin/composer',
        ])->render();
    }
}
