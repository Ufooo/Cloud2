<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\SiteProvisioningStep;
use Nip\Site\Enums\SiteStatus;

class FinalizeSiteJob extends BaseSiteProvisionJob
{
    protected function getRunAsUser(): ?string
    {
        return $this->site->user;
    }

    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::FinalizingSite;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.finalize-site', [
            'site' => $this->site,
            'siteRoot' => $this->site->getSiteRoot(),
            'applicationPath' => $this->site->getApplicationPath(),
            'user' => $this->site->user,
            'domain' => $this->site->domain,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        parent::handleSuccess($result);

        $this->site->update([
            'status' => SiteStatus::Installed,
        ]);

        // Enable all domain records
        $this->site->domainRecords()->update([
            'status' => DomainRecordStatus::Enabled,
        ]);
    }
}
