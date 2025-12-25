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

        return <<<BASH
#!/bin/bash
set -e

echo "Configuring firewall rule for port {$port}"

# Ensure UFW is installed
if ! command -v ufw >/dev/null 2>&1; then
    echo "UFW not installed, installing..."
    apt-get update && apt-get install -y ufw
fi

# Enable UFW if not already enabled
if ufw status | grep -q "Status: inactive"; then
    echo "Enabling UFW"
    ufw --force enable
fi

# Add the firewall rule
echo "Adding firewall rule: {$rule->name}"
{$ufwRule}

# Reload UFW
echo "Reloading firewall"
ufw reload

# Verify the rule was added
echo "Verifying firewall rule"
if ufw status numbered | grep -q "{$port}"; then
    echo "Firewall rule for port {$port} verified"
else
    echo "Warning: Could not verify firewall rule for port {$port}"
    exit 1
fi

echo "Firewall rule installed successfully"
BASH;
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
