<?php

namespace Nip\SecurityMonitor\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nip\SecurityMonitor\Actions\RunSecurityScan;
use Nip\Server\Models\Server;

class RunSecurityScanJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    /** @param array<int> $siteIds */
    public function __construct(
        public int $serverId,
        public array $siteIds,
    ) {}

    public function handle(RunSecurityScan $action): void
    {
        $server = Server::findOrFail($this->serverId);
        $action->handle($server, $this->siteIds);
    }

    public function uniqueId(): string
    {
        return "security-scan:{$this->serverId}";
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return ['security-scan', "server:{$this->serverId}"];
    }
}
