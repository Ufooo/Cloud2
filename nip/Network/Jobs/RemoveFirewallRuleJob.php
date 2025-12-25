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
        $port = $this->rule->port;
        $type = $this->rule->type->value === 'allow' ? 'ALLOW' : 'DENY';

        return <<<BASH
#!/bin/bash
set -e

echo "Deleting firewall rule for port {$port}"

# Find rule by port and type
RULE_NUMBERS=\$(ufw status numbered | grep "{$port}" | grep "{$type}" | grep -oP '^\\[\\s*\\K[0-9]+' | sort -rn)

if [ -z "\$RULE_NUMBERS" ]; then
    echo "Warning: Firewall rule for port {$port} not found in UFW"
    exit 0  # Not an error - rule might already be deleted
fi

# Delete each matching rule (in reverse order to maintain rule numbers)
for RULE_NUM in \$RULE_NUMBERS; do
    echo "Deleting UFW rule number \$RULE_NUM"
    echo "y" | ufw delete \$RULE_NUM

    if [ \$? -eq 0 ]; then
        echo "Successfully deleted UFW rule \$RULE_NUM"
    else
        echo "Error: Failed to delete UFW rule \$RULE_NUM"
        exit 1
    fi
done

# Reload UFW
echo "Reloading firewall"
ufw reload

echo "Firewall rule for port {$port} successfully removed"
BASH;
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
