<?php

namespace Nip\Php\Jobs;

use Nip\Php\Models\PhpSetting;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class UpdatePhpSettingsJob extends BaseProvisionJob
{
    public int $timeout = 300;

    public function __construct(
        public PhpSetting $phpSetting
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'php_settings';
    }

    protected function getResourceId(): ?int
    {
        return $this->phpSetting->id;
    }

    protected function getServer(): Server
    {
        return $this->phpSetting->server;
    }

    protected function generateScript(): string
    {
        $server = $this->phpSetting->server;
        $versions = $server->phpVersions()
            ->where('status', 'installed')
            ->pluck('version')
            ->toArray();

        return view('provisioning.scripts.php.update-settings', [
            'versions' => $versions,
            'maxUploadSize' => $this->phpSetting->max_upload_size ?? 100,
            'maxExecutionTime' => $this->phpSetting->max_execution_time ?? 60,
            'opcacheEnabled' => $this->phpSetting->opcache_enabled ?? true,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        // Settings are already saved in the database
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Log the failure, settings remain in database for retry
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'php_settings',
            'php_settings:'.$this->phpSetting->id,
            'server:'.$this->phpSetting->server_id,
        ];
    }
}
