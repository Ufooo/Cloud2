<?php

namespace Nip\Php\Jobs;

use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class RemovePhpVersionJob extends BaseProvisionJob
{
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
        $this->phpVersion->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Restore to Installed - the PHP version is still on the server
        $this->phpVersion->update([
            'status' => PhpVersionStatus::Installed,
        ]);
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
