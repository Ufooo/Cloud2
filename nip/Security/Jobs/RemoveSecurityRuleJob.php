<?php

namespace Nip\Security\Jobs;

use Nip\Security\Enums\SecurityRuleStatus;
use Nip\Security\Jobs\Concerns\PreventsOverlappingSecuritySyncs;
use Nip\Security\Models\SecurityRule;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class RemoveSecurityRuleJob extends BaseProvisionJob
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

        // Get remaining rules (excluding the one being removed)
        $remainingRules = $site->securityRules()
            ->with('credentials')
            ->where('id', '!=', $this->securityRule->id)
            ->where('status', '!=', SecurityRuleStatus::Removing)
            ->orderBy('id')
            ->get();

        return view('provisioning.scripts.security.sync', [
            'site' => $site,
            'rules' => $remainingRules,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->securityRule->delete();
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
            'remove',
        ];
    }
}
