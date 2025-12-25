<?php

namespace Nip\Database\Jobs;

use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Models\Database;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class CreateDatabaseJob extends BaseProvisionJob
{
    public int $timeout = 120;

    public function __construct(
        public Database $database
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'database';
    }

    protected function getResourceId(): ?int
    {
        return null;
    }

    protected function getServer(): Server
    {
        return $this->database->server;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.database.create', [
            'databaseName' => $this->database->name,
            'mysqlRootPassword' => $this->database->server->database_password,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->database->update([
            'status' => DatabaseStatus::Installed,
        ]);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->database->update([
            'status' => DatabaseStatus::Failed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'database_create',
            'database:'.$this->database->id,
            'server:'.$this->database->server_id,
        ];
    }
}
