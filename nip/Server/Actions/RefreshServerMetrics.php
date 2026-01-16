<?php

namespace Nip\Server\Actions;

use Exception;
use Illuminate\Support\Facades\Log;
use Nip\Server\Jobs\CollectServerMetricsJob;
use Nip\Server\Models\Server;

class RefreshServerMetrics
{
    public function handle(Server $server): bool
    {
        try {
            CollectServerMetricsJob::dispatchSync($server);

            return true;
        } catch (Exception $e) {
            Log::warning('Server metrics refresh failed, status set to Disconnected', [
                'server_id' => $server->id,
                'server_name' => $server->name,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
