<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Site\Enums\SiteProvisioningStep;
use Nip\Site\Enums\WwwRedirectType;

class ConfigureWwwRedirectJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::ConfiguringWwwRedirect;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.configure-www-redirect', [
            'site' => $this->site,
            'domain' => $this->site->domain,
            'wwwRedirectConfig' => $this->generateWwwRedirectConfig(),
        ])->render();
    }

    /**
     * The nginx config of the shared www redirect partial the Domain path renders. No certificate
     * exists while a site is being provisioned, so it only holds the plain http redirect, and it is
     * empty when the site wants no redirect. A redirect to the primary domain has no meaning for
     * the primary domain itself, the Domain path skips it as well.
     */
    protected function generateWwwRedirectConfig(): string
    {
        if ($this->site->www_redirect_type === WwwRedirectType::ToPrimary) {
            return '';
        }

        return view('provisioning.scripts.partials.nginx-www-redirect', [
            'site' => $this->site,
            'domain' => $this->site->domain,
            'wwwRedirectType' => $this->site->www_redirect_type,
            'allowWildcard' => $this->site->allow_wildcard,
            'certificate' => null,
        ])->render();
    }
}
