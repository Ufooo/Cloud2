<?php

namespace Nip\Network\Jobs;

use Nip\Network\Enums\RuleStatus;
use Nip\Network\Models\FirewallRule;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class RemoveFirewallRuleJob extends BaseProvisionJob
{
    public int $timeout = 120;

    public function __construct(
        public FirewallRule $rule
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'firewall_rule';
    }

    protected function getResourceId(): ?int
    {
        return $this->rule->id;
    }

    protected function getServer(): Server
    {
        return $this->rule->server;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.firewall.remove', [
            'port' => $this->rule->port,
            'type' => $this->rule->type->value === 'allow' ? 'ALLOW' : 'DENY',
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->rule->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Restore to Installed - the rule is still on the server
        $this->rule->update([
            'status' => RuleStatus::Installed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'firewall_rule_remove',
            'firewall_rule:'.$this->rule->id,
            'server:'.$this->rule->server_id,
        ];
    }
}
