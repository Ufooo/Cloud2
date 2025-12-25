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

        return view('provisioning.scripts.ssh-key.sync', [
            'username' => $username,
            'homeDir' => $username === 'root' ? '/root' : "/home/{$username}",
            'publicKey' => trim($this->sshKey->public_key),
            'keyId' => $this->sshKey->id,
            'keyName' => $this->sshKey->name,
        ])->render();
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
