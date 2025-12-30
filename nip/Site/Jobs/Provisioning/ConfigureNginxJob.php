<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;

class ConfigureNginxJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::ConfiguringNginx;
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

        $isolatedFpmScript = view('provisioning.scripts.site.partials.isolated-fpm-pool', [
            'phpVersion' => $this->site->getEffectivePhpVersion(),
            'domain' => $this->site->domain,
            'user' => $this->site->user,
            'fullPath' => $this->site->getFullPath(),
        ])->render();

        return view('provisioning.scripts.site.steps.configure-nginx', [
            'site' => $this->site,
            'domain' => $this->site->domain,
            'nginxConfig' => $nginxConfig,
            'isolatedFpmScript' => $isolatedFpmScript,
        ])->render();
    }
}
