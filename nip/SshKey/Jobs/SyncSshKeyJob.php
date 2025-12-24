<?php

namespace Nip\SshKey\Jobs;

use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\SshKey\Enums\SshKeyStatus;
use Nip\SshKey\Models\SshKey;

class SyncSshKeyJob extends BaseProvisionJob
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
        $keyId = $this->sshKey->id;

        return <<<BASH
#!/bin/bash
set -e

USERNAME="{$username}"
HOME_DIR="{$homeDir}"
SSH_DIR="\${HOME_DIR}/.ssh"
AUTH_KEYS="\${SSH_DIR}/authorized_keys"
PUBLIC_KEY="{$publicKey}"
KEY_COMMENT="# Netipar[{$keyId}]: {$keyName}"

echo "Setting up SSH key for user: \${USERNAME}"

# Create .ssh directory if not exists
if [ ! -d "\${SSH_DIR}" ]; then
    mkdir -p "\${SSH_DIR}"
    chown \${USERNAME}:\${USERNAME} "\${SSH_DIR}"
    chmod 700 "\${SSH_DIR}"
    echo "Created \${SSH_DIR}"
fi

# Create authorized_keys if not exists
if [ ! -f "\${AUTH_KEYS}" ]; then
    touch "\${AUTH_KEYS}"
    chown \${USERNAME}:\${USERNAME} "\${AUTH_KEYS}"
    chmod 600 "\${AUTH_KEYS}"
    echo "Created \${AUTH_KEYS}"
fi

# Check if key already exists (by fingerprint content)
if grep -qF "\${PUBLIC_KEY}" "\${AUTH_KEYS}" 2>/dev/null; then
    echo "SSH key already exists in authorized_keys"
    exit 0
fi

# Add the key with comment
echo "" >> "\${AUTH_KEYS}"
echo "\${KEY_COMMENT}" >> "\${AUTH_KEYS}"
echo "\${PUBLIC_KEY}" >> "\${AUTH_KEYS}"

# Fix permissions
chown \${USERNAME}:\${USERNAME} "\${AUTH_KEYS}"
chmod 600 "\${AUTH_KEYS}"

echo "SSH key successfully added for user \${USERNAME}"
BASH;
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->sshKey->update([
            'status' => SshKeyStatus::Installed,
        ]);
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
            'ssh_key',
            'ssh_key:'.$this->sshKey->id,
            'server:'.$this->sshKey->server_id,
            'unix_user:'.$this->sshKey->unix_user_id,
        ];
    }
}
