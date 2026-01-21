<?php

namespace Nip\Server\Console\Commands;

use Illuminate\Console\Command;
use Nip\Server\Jobs\CollectServerMetricsJob;
use Nip\Server\Models\Server;

class CollectServerMetricsCommand extends Command
{
    protected $signature = 'servers:collect-metrics {--sync : Run synchronously instead of queuing}';

    protected $description = 'Collect metrics from all configured servers';

    public function handle(): int
    {
        $servers = Server::query()
            ->whereNotNull('ip_address')
            ->whereNotNull('ssh_private_key')
            ->get();

        if ($servers->isEmpty()) {
            $this->info('No active servers found.');

            return self::SUCCESS;
        }

        $sync = $this->option('sync');
        $method = $sync ? 'synchronously' : 'via queue';

        $this->info("Collecting metrics from {$servers->count()} server(s) {$method}...");

        foreach ($servers as $server) {
            $this->line("  - {$server->name} ({$server->ip_address})");

            if ($sync) {
                CollectServerMetricsJob::dispatchSync($server);
            } else {
                CollectServerMetricsJob::dispatch($server);
            }
        }

        $this->info('Metrics collection '.($sync ? 'completed' : 'jobs dispatched').'.');

        return self::SUCCESS;
    }
}
