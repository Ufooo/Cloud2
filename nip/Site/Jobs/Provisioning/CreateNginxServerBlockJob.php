<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class CreateNginxServerBlockJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::CreatingNginxServerBlock;
    }

    protected function generateScript(): string
    {
        $nginxConfig = view('provisioning.scripts.site.partials.nginx-config', [
            'site' => $this->site,
            'user' => $this->site->user,
            'domain' => $this->site->domain,
            'fullPath' => $this->site->getFullPath(),
            'rootPath' => $this->site->getRootPath(),
            'phpSocket' => $this->site->getPhpSocketPath(),
            'phpVersion' => $this->site->getEffectivePhpVersion(),
            'siteType' => $this->site->type,
            'allowWildcard' => $this->site->allow_wildcard,
            'wwwRedirectType' => $this->site->www_redirect_type,
        ])->render();

        return view('provisioning.scripts.site.steps.create-nginx-config', [
            'site' => $this->site,
            'domain' => $this->site->domain,
            'nginxConfig' => $nginxConfig,
        ])->render();
    }
}
