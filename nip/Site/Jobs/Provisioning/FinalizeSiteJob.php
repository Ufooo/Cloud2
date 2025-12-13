<?php

namespace Nip\Site\Jobs\Provisioning;

use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\SiteProvisioningStep;
use Nip\Site\Enums\SiteStatus;

class FinalizeSiteJob extends BaseSiteProvisionJob
{
    protected function getStep(): SiteProvisioningStep
    {
        return SiteProvisioningStep::FinishingUp;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.site.steps.finalize-site', [
            'site' => $this->site,
            'fullPath' => $this->site->getFullPath(),
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
    }
}
