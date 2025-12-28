<?php

namespace Nip\Database\Jobs;

use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Models\Database;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Events\SiteResourceStatusUpdated;

class DeleteDatabaseJob extends BaseProvisionJob
{
    public int $tries = 1;

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
        return view('provisioning.scripts.database.delete', [
            'databaseName' => $this->database->name,
            'mysqlRootPassword' => $this->database->server->database_password,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $server = $this->database->server;
        $site = $this->database->site;
        $siteId = $this->database->site_id;
        $databaseId = $this->database->id;

        $this->database->delete();

        if ($siteId && $site) {
            SiteResourceStatusUpdated::dispatch(
                $site,
                'database',
                $databaseId,
                'deleted'
            );
        } else {
            ServerResourceStatusUpdated::dispatch(
                $server,
                'database',
                $databaseId,
                'deleted'
            );
        }
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->database->update([
            'status' => DatabaseStatus::Installed,
        ]);

        if ($this->database->site_id) {
            SiteResourceStatusUpdated::dispatch(
                $this->database->site,
                'database',
                $this->database->id,
                DatabaseStatus::Installed->value
            );
        } else {
            ServerResourceStatusUpdated::dispatch(
                $this->database->server,
                'database',
                $this->database->id,
                DatabaseStatus::Installed->value
            );
        }
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'database_delete',
            'database:'.$this->database->id,
            'server:'.$this->database->server_id,
        ];
    }
}
