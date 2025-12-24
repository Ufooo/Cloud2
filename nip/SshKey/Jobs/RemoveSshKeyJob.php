<?php

namespace Nip\SshKey\Jobs;

use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\SshKey\Enums\SshKeyStatus;
use Nip\SshKey\Models\SshKey;

class RemoveSshKeyJob extends BaseProvisionJob
{
    public int $timeout = 120;

    public function __construct(
        public SshKey $sshKey
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'ssh_key';
    }

    protected function getResourceId(): ?int
    {
        return $this->sshKey->id;
    }

    protected function getServer(): Server
    {
        return $this->sshKey->server;
    }

    protected function generateScript(): string
    {
        $unixUser = $this->sshKey->unixUser;
        $username = $unixUser->username;
        $publicKey = trim($this->sshKey->public_key);
        $keyName = $this->sshKey->name;

        $homeDir = $username === 'root' ? '/root' : "/home/{$username}";
        $escapedKey = addslashes($publicKey);

        return <<<BASH
#!/bin/bash
set -e

AUTH_KEYS="{$homeDir}/.ssh/authorized_keys"

if [ -f "\${AUTH_KEYS}" ]; then
    # Create temp file without the key and its comment
    grep -v "# Netipar: {$keyName}" "\${AUTH_KEYS}" | grep -v "{$escapedKey}" > "\${AUTH_KEYS}.tmp" || true
    mv "\${AUTH_KEYS}.tmp" "\${AUTH_KEYS}"
    chown {$username}:{$username} "\${AUTH_KEYS}"
    chmod 600 "\${AUTH_KEYS}"
    echo "SSH key removed successfully"
else
    echo "authorized_keys file not found"
fi
BASH;
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->sshKey->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->sshKey->update([
            'status' => SshKeyStatus::Failed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'ssh_key_remove',
            'ssh_key:'.$this->sshKey->id,
            'server:'.$this->sshKey->server_id,
            'unix_user:'.$this->sshKey->unix_user_id,
        ];
    }
}
