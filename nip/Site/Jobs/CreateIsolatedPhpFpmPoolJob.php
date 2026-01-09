<?php

namespace Nip\Site\Jobs;

use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Shared\Traits\ResolvesPhpVersion;

class CreateIsolatedPhpFpmPoolJob extends BaseProvisionJob
{
    use ResolvesPhpVersion;

    public int $timeout = 60;

    public function __construct(
        public Server $server,
        public string $user,
        public string $phpVersion
    ) {
        $this->onQueue('provisioning');
    }

    protected function getResourceType(): string
    {
        return 'isolated_php_fpm_pool';
    }

    protected function getResourceId(): ?int
    {
        return null;
    }

    protected function getServer(): Server
    {
        return $this->server;
    }

    protected function generateScript(): string
    {
        return view('provisioning.scripts.php.isolated-pool-create', [
            'user' => $this->user,
            'phpVersion' => $this->resolvePhpVersionString($this->phpVersion),
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        //
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'isolated_php_fpm_pool',
            'server:'.$this->server->id,
            'user:'.$this->user,
            'php:'.$this->phpVersion,
        ];
    }
}
