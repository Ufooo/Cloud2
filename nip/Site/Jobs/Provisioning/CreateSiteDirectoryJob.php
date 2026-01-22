<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Server\Models\Server;
use Nip\Site\Enums\SiteProvisioningStep;

class CreateSiteDirectoryJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::CreatingSiteDirectory;
    }

    protected function getServer(): Server
    {
        return $this->site->server;
    }

    protected function getRunAsUser(): ?string
    {
        return $this->site->user;
    }

    protected function generateScript(): string
    {
        $defaultIndexScript = view('provisioning.scripts.site.partials.default-index-html', [
            'webDirectory' => $this->site->web_directory,
            'domain' => $this->site->domain,
        ])->render();

        return view('provisioning.scripts.site.steps.create-site-directory', [
            'site' => $this->site,
            'user' => $this->site->user,
            'domain' => $this->site->domain,
            'siteRoot' => $this->site->getSiteRoot(),
            'rootDirectory' => $this->site->root_directory,
            'webDirectory' => $this->site->web_directory,
            'siteType' => $this->site->type,
            'defaultIndexScript' => $defaultIndexScript,
        ])->render();
    }
}
