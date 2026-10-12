<?php

namespace Nip\Security\Jobs;

use Nip\Security\Enums\SecurityRuleStatus;
use Nip\Security\Jobs\Concerns\PreventsOverlappingSecuritySyncs;
use Nip\Security\Models\SecurityRule;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class AddSecurityRuleJob extends BaseProvisionJob
{
    use PreventsOverlappingSecuritySyncs;

    public function __construct(
        public SecurityRule $securityRule,
    ) {}

    protected function getResourceType(): string
    {
        return 'security';
    }

    protected function getResourceId(): ?int
    {
        return $this->securityRule->id;
    }

    protected function getServer(): Server
    {
        return $this->securityRule->site->server;
    }

    protected function generateScript(): string
    {
        $site = $this->securityRule->site;

        return view('provisioning.scripts.security.sync', [
            'site' => $site,
            'rules' => $site->securityRules()
                ->with('credentials')
                ->where('status', '!=', SecurityRuleStatus::Removing)
                ->orderBy('id')
                ->get(),
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->securityRule->update([
            'status' => SecurityRuleStatus::Installed,
        ]);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->securityRule->update([
            'status' => SecurityRuleStatus::Failed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'provision',
            'security',
            'security:'.$this->securityRule->id,
            'site:'.$this->securityRule->site_id,
            'server:'.$this->securityRule->site->server_id,
            'add',
        ];
    }
}
