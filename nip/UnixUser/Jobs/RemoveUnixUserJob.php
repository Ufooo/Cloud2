<?php

namespace Nip\UnixUser\Jobs;

use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\UnixUser\Enums\UserStatus;
use Nip\UnixUser\Models\UnixUser;

class RemoveUnixUserJob extends BaseProvisionJob
{
    public int $timeout = 120;

    public function __construct(
        public UnixUser $unixUser
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'unix_user';
    }

    protected function getResourceId(): ?int
    {
        return $this->unixUser->id;
    }

    protected function getServer(): Server
    {
        return $this->unixUser->server;
    }

    protected function generateScript(): string
    {
        $username = $this->unixUser->username;

        return view('provisioning.scripts.unix-user.remove', [
            'username' => $username,
            'homeDir' => $username === 'root' ? '/root' : "/home/{$username}",
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->unixUser->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->unixUser->update([
            'status' => UserStatus::Installed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'unix_user_remove',
            'unix_user:'.$this->unixUser->id,
            'server:'.$this->unixUser->server_id,
        ];
    }
}
