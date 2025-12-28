<?php

namespace Nip\Php\Jobs;

use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class RemovePhpVersionJob extends BaseProvisionJob
{
    public int $tries = 1;

    public int $timeout = 300;

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
        return view('provisioning.scripts.php.uninstall', [
            'version' => $this->phpVersion->version,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $server = $this->phpVersion->server;
        $phpVersionId = $this->phpVersion->id;

        $this->phpVersion->delete();

        ServerResourceStatusUpdated::dispatch(
            $server,
            'php_version',
            $phpVersionId,
            'deleted'
        );
    }

    protected function handleFailure(\Throwable $exception): void
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

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'php_version_remove',
            'php_version:'.$this->phpVersion->id,
            'server:'.$this->phpVersion->server_id,
        ];
    }
}
