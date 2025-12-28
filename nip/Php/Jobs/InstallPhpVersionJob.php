<?php

namespace Nip\Php\Jobs;

use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class InstallPhpVersionJob extends BaseProvisionJob
{
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public PhpVersion $phpVersion
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'php_version';
    }

    protected function getResourceId(): ?int
    {
        return $this->phpVersion->id;
    }

    protected function getServer(): Server
    {
        return $this->phpVersion->server;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.php.install', [
            'version' => $this->phpVersion->version,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->phpVersion->update([
            'status' => PhpVersionStatus::Installed,
        ]);

        ServerResourceStatusUpdated::dispatch(
            $this->phpVersion->server,
            'php_version',
            $this->phpVersion->id,
            PhpVersionStatus::Installed->value
        );
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->phpVersion->update([
            'status' => PhpVersionStatus::Failed,
        ]);

        ServerResourceStatusUpdated::dispatch(
            $this->phpVersion->server,
            'php_version',
            $this->phpVersion->id,
            PhpVersionStatus::Failed->value
        );
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'php_version_install',
            'php_version:'.$this->phpVersion->id,
            'server:'.$this->phpVersion->server_id,
        ];
    }
}
