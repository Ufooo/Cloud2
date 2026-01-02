<?php

namespace Nip\Database\Jobs;

use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Models\Database;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseCommandJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Events\SiteResourceStatusUpdated;

class CreateDatabaseJob extends BaseCommandJob
{
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public Database $database
    ) {
        $this->onQueue('provisioning');
    }

    protected function getServer(): Server
    {
        return $this->database->server;
    }

    protected function getCommand(): string
    {
        $password = $this->database->server->database_password;
        $name = $this->database->name;

        return "mysql --user=\"root\" --password=\"{$password}\" -e \"CREATE DATABASE \`{$name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\"";
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->database->update([
            'status' => DatabaseStatus::Installed,
        ]);

        $this->dispatchStatusUpdate(DatabaseStatus::Installed->value);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->database->update([
            'status' => DatabaseStatus::Failed,
        ]);

        $this->dispatchStatusUpdate(DatabaseStatus::Failed->value);
    }

    protected function dispatchStatusUpdate(string $status): void
    {
        if ($this->database->site_id) {
            SiteResourceStatusUpdated::dispatch(
                $this->database->site,
                'database',
                $this->database->id,
                $status
            );
        } else {
            ServerResourceStatusUpdated::dispatch(
                $this->database->server,
                'database',
                $this->database->id,
                $status
            );
        }
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
