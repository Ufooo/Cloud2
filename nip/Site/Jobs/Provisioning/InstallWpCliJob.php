<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Server\Models\Server;
use Nip\Site\Enums\SiteProvisioningStep;

class InstallWpCliJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::InstallingWpCli;
    }

    protected function getServer(): Server
    {
        return $this->site->server;
    }

    protected function getRunAsUser(): ?string
    {
        return null;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.install-wp-cli')->render();
    }
}
