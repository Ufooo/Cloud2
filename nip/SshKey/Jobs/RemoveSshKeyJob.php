<?php

namespace Nip\SshKey\Jobs;

use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\SshKey\Enums\SshKeyStatus;
use Nip\SshKey\Models\SshKey;

class RemoveSshKeyJob extends BaseProvisionJob
{
    public int $tries = 1;

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

        return view('provisioning.scripts.ssh-key.remove', [
            'username' => $username,
            'homeDir' => $username === 'root' ? '/root' : "/home/{$username}",
            'keyId' => $this->sshKey->id,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $server = $this->sshKey->server;
        $sshKeyId = $this->sshKey->id;

        $this->sshKey->delete();

        ServerResourceStatusUpdated::dispatch(
            $server,
            'ssh_key',
            $sshKeyId,
            'deleted'
        );
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->sshKey->update([
            'status' => SshKeyStatus::Installed,
        ]);

        ServerResourceStatusUpdated::dispatch(
            $this->sshKey->server,
            'ssh_key',
            $this->sshKey->id,
            SshKeyStatus::Installed->value
        );
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
