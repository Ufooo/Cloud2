<?php

namespace Nip\Database\Jobs;

use Nip\Database\Enums\DatabaseUserStatus;
use Nip\Database\Models\DatabaseUser;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class DeleteDatabaseUserJob extends BaseProvisionJob
{
    public int $tries = 1;

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
        return view('provisioning.scripts.database.delete-user', [
            'username' => $this->databaseUser->username,
            'serverIp' => $this->databaseUser->server->ip_address,
            'mysqlRootPassword' => $this->databaseUser->server->database_password,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $server = $this->databaseUser->server;
        $databaseUserId = $this->databaseUser->id;

        $this->databaseUser->delete();

        ServerResourceStatusUpdated::dispatch(
            $server,
            'database_user',
            $databaseUserId,
            'deleted'
        );
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->databaseUser->update([
            'status' => DatabaseUserStatus::Installed,
        ]);

        ServerResourceStatusUpdated::dispatch(
            $this->databaseUser->server,
            'database_user',
            $this->databaseUser->id,
            DatabaseUserStatus::Installed->value
        );
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'database_user_delete',
            'database_user:'.$this->databaseUser->id,
            'server:'.$this->databaseUser->server_id,
        ];
    }
}
