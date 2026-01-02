<?php

namespace Nip\Php\Jobs;

use Illuminate\Support\Facades\DB;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseCommandJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class SetCliDefaultPhpVersionJob extends BaseCommandJob
{
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public PhpVersion $phpVersion
    ) {
        $this->onQueue('provisioning');
    }

    protected function getServer(): Server
    {
        return $this->phpVersion->server;
    }

    protected function getCommand(): string
    {
        return "update-alternatives --set php /usr/bin/php{$this->phpVersion->version}";
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $server = $this->phpVersion->server;

        DB::transaction(function () use ($server) {
            $server->phpVersions()->update(['is_cli_default' => false]);
            $this->phpVersion->update(['is_cli_default' => true]);
        });

        ServerResourceStatusUpdated::dispatch(
            $server,
            'php_version',
            $this->phpVersion->id,
            'cli_default_set'
        );
    }

    protected function handleFailure(\Throwable $exception): void
    {
        ServerResourceStatusUpdated::dispatch(
            $this->phpVersion->server,
            'php_version',
            $this->phpVersion->id,
            'cli_default_failed'
        );
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'php_cli_default',
            'php_version:'.$this->phpVersion->id,
            'server:'.$this->phpVersion->server_id,
        ];
    }
}
