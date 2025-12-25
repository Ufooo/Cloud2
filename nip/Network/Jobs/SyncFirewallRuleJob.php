<?php

namespace Nip\Network\Jobs;

use Nip\Network\Enums\RuleStatus;
use Nip\Network\Models\FirewallRule;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class SyncFirewallRuleJob extends BaseProvisionJob
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
        $rule = $this->rule;
        $port = $rule->port;
        $type = $rule->type->value;
        $ipAddress = $rule->ip_address;

        $ufwCommand = $type === 'allow' ? 'allow' : 'deny';

        if ($ipAddress) {
            $ufwRule = "ufw {$ufwCommand} from {$ipAddress} to any port {$port}";
        } else {
            $ufwRule = "ufw {$ufwCommand} {$port}";
        }

        return view('provisioning.scripts.firewall.sync', [
            'port' => $port,
            'ruleName' => $rule->name,
            'ufwRule' => $ufwRule,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->rule->update([
            'status' => RuleStatus::Installed,
        ]);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->rule->update([
            'status' => RuleStatus::Failed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'firewall_rule',
            'firewall_rule:'.$this->rule->id,
            'server:'.$this->rule->server_id,
        ];
    }
}
