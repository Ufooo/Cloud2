<?php

namespace Nip\Database\Jobs;

use Nip\Database\Enums\DatabaseUserStatus;
use Nip\Database\Models\DatabaseUser;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class SyncDatabaseUserJob extends BaseProvisionJob
{
    public int $timeout = 120;

    public function __construct(
        public DatabaseUser $databaseUser
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'database_user';
    }

    protected function getResourceId(): ?int
    {
        return null;
    }

    protected function getServer(): Server
    {
        return $this->databaseUser->server;
    }

    protected function generateScript(): string
    {
        $server = $this->databaseUser->server;
        $databases = $this->databaseUser->databases()->pluck('name')->toArray();

        return view('provisioning.scripts.database.sync-user', [
            'username' => $this->databaseUser->username,
            'password' => $this->databaseUser->password,
            'serverIp' => $server->ip_address,
            'databases' => $databases,
            'readonly' => $this->databaseUser->readonly,
            'mysqlRootPassword' => $server->database_password,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->databaseUser->update([
            'status' => DatabaseUserStatus::Installed,
        ]);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->databaseUser->update([
            'status' => DatabaseUserStatus::Failed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'database_user_sync',
            'database_user:'.$this->databaseUser->id,
            'server:'.$this->databaseUser->server_id,
        ];
    }
}
