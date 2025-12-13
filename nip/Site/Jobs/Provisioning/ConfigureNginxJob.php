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
        $phpVersion = $this->site->php_version ?? $this->site->server->php_version;
        $fullPath = $this->site->getFullPath();
        $webDirectory = $this->site->web_directory;
        $domain = $this->site->domain;
        $user = $this->site->user;

        $nginxConfig = view('provisioning.scripts.site.partials.nginx-config', [
            'site' => $this->site,
            'user' => $user,
            'domain' => $domain,
            'fullPath' => $fullPath,
            'webDirectory' => $webDirectory,
            'phpVersion' => $phpVersion,
            'siteType' => $this->site->type,
            'allowWildcard' => $this->site->allow_wildcard,
            'wwwRedirectType' => $this->site->www_redirect_type,
            'isIsolated' => $this->site->is_isolated,
        ])->render();

        $isolatedFpmScript = '';
        if ($this->site->is_isolated) {
            $isolatedFpmScript = view('provisioning.scripts.site.partials.isolated-fpm-pool', [
                'phpVersion' => $phpVersion,
                'domain' => $domain,
                'user' => $user,
                'fullPath' => $fullPath,
            ])->render();
        }

        return view('provisioning.scripts.site.steps.configure-nginx', [
            'site' => $this->site,
            'domain' => $domain,
            'nginxConfig' => $nginxConfig,
            'isolatedFpmScript' => $isolatedFpmScript,
        ])->render();
    }
}
