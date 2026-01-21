<?php

namespace Nip\Server\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Events\ServerMetricsUpdated;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\SSHService;
use Throwable;

class CollectServerMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    /** @var array<int> */
    public array $backoff = [10, 30];

    public function __construct(
        public Server $server
    ) {}

    public function handle(SSHService $ssh): void
    {
        try {
            $ssh->setTimeout(30);
            $ssh->connect($this->server);

            $metrics = $this->fetchMetrics($ssh);

            $this->server->update([
                'status' => ServerStatus::Connected,
                'uptime_seconds' => $metrics['uptime_seconds'],
                'load_avg_1' => $metrics['load_avg_1'],
                'load_avg_5' => $metrics['load_avg_5'],
                'load_avg_15' => $metrics['load_avg_15'],
                'cpu_percent' => $metrics['cpu_percent'],
                'ram_total_bytes' => $metrics['ram_total_bytes'],
                'ram_used_bytes' => $metrics['ram_used_bytes'],
                'ram_percent' => $metrics['ram_percent'],
                'disk_total_bytes' => $metrics['disk_total_bytes'],
                'disk_used_bytes' => $metrics['disk_used_bytes'],
                'disk_percent' => $metrics['disk_percent'],
                'last_metrics_at' => now(),
                'last_connected_at' => now(),
            ]);

            // Broadcast safely - don't let broadcast failures affect server status
            try {
                ServerMetricsUpdated::dispatch($this->server);
            } catch (Throwable) {
                // Silently ignore - Reverb may be unavailable
            }
        } catch (Exception $e) {
            $this->handleFailure($e);

            throw $e;
        } finally {
            $ssh->disconnect();
        }
    }

    /**
     * @return array{uptime_seconds: int, load_avg_1: float, load_avg_5: float, load_avg_15: float, cpu_percent: int, ram_total_bytes: int, ram_used_bytes: int, ram_percent: int, disk_total_bytes: int, disk_used_bytes: int, disk_percent: int}
     */
    private function fetchMetrics(SSHService $ssh): array
    {
        $uptimeResult = $ssh->exec('cat /proc/uptime');
        $uptimeSeconds = (int) explode(' ', trim($uptimeResult->output))[0];

        $loadResult = $ssh->exec('cat /proc/loadavg');
        $loadParts = explode(' ', trim($loadResult->output));
        $loadAvg1 = (float) $loadParts[0];
        $loadAvg5 = (float) $loadParts[1];
        $loadAvg15 = (float) $loadParts[2];

        $cpuResult = $ssh->exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2}'");
        $cpuPercent = (int) round((float) trim($cpuResult->output));

        $ramResult = $ssh->exec("free -b | grep Mem | awk '{print $2, $3}'");
        $ramParts = explode(' ', trim($ramResult->output));
        $ramTotalBytes = (int) ($ramParts[0] ?? 0);
        $ramUsedBytes = (int) ($ramParts[1] ?? 0);
        $ramPercent = $ramTotalBytes > 0 ? (int) round(($ramUsedBytes / $ramTotalBytes) * 100) : 0;

        $diskResult = $ssh->exec("df -B1 / | tail -1 | awk '{print $2, $3, $5}'");
        $diskParts = explode(' ', trim($diskResult->output));
        $diskTotalBytes = (int) ($diskParts[0] ?? 0);
        $diskUsedBytes = (int) ($diskParts[1] ?? 0);
        $diskPercent = (int) str_replace('%', '', $diskParts[2] ?? '0');

        return [
            'uptime_seconds' => $uptimeSeconds,
            'load_avg_1' => $loadAvg1,
            'load_avg_5' => $loadAvg5,
            'load_avg_15' => $loadAvg15,
            'cpu_percent' => $cpuPercent,
            'ram_total_bytes' => $ramTotalBytes,
            'ram_used_bytes' => $ramUsedBytes,
            'ram_percent' => $ramPercent,
            'disk_total_bytes' => $diskTotalBytes,
            'disk_used_bytes' => $diskUsedBytes,
            'disk_percent' => $diskPercent,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        $this->handleFailure($exception);
    }

    private function handleFailure(\Throwable $exception): void
    {
        $this->server->update(['status' => ServerStatus::Disconnected]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'metrics',
            'server:'.$this->server->id,
        ];
    }
}
