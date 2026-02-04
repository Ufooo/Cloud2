<?php

namespace Nip\Network\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Nip\Network\Models\OpenVpnClient;

class SyncOpenVpnClientsCommand extends Command
{
    protected $signature = 'openvpn:sync {--status-file=/var/log/openvpn/status.log : Path to OpenVPN status log}';

    protected $description = 'Sync OpenVPN clients from status log file';

    public function handle(): int
    {
        $statusFile = $this->option('status-file');

        if (! file_exists($statusFile)) {
            $this->error("Status file not found: {$statusFile}");

            return self::FAILURE;
        }

        $content = file_get_contents($statusFile);
        $clients = $this->parseStatusLog($content);

        // Mark all clients as offline first
        OpenVpnClient::query()->update(['is_online' => false]);

        $synced = 0;
        foreach ($clients as $client) {
            OpenVpnClient::updateOrCreate(
                ['common_name' => $client['common_name']],
                [
                    'real_address' => $client['real_address'],
                    'virtual_address' => $client['virtual_address'],
                    'bytes_received' => $client['bytes_received'],
                    'bytes_sent' => $client['bytes_sent'],
                    'connected_since' => $client['connected_since'],
                    'is_online' => true,
                    'last_seen_at' => now(),
                ]
            );
            $synced++;
        }

        $this->info("Synced {$synced} OpenVPN clients");

        return self::SUCCESS;
    }

    private function parseStatusLog(string $content): array
    {
        $lines = explode("\n", $content);
        $clients = [];
        $routes = [];

        $section = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Detect section changes
            if (str_starts_with($line, 'Common Name,Real Address')) {
                $section = 'clients';

                continue;
            }
            if (str_starts_with($line, 'ROUTING TABLE')) {
                $section = 'routes';

                continue;
            }
            if (str_starts_with($line, 'GLOBAL STATS') || str_starts_with($line, 'END')) {
                $section = null;

                continue;
            }
            if (str_starts_with($line, 'Virtual Address,Common Name')) {
                continue;
            }

            if ($section === 'clients' && ! empty($line)) {
                $parts = str_getcsv($line);
                if (count($parts) >= 5 && ! empty($parts[0])) {
                    $clients[$parts[0]] = [
                        'common_name' => $parts[0],
                        'real_address' => $parts[1],
                        'bytes_received' => (int) $parts[2],
                        'bytes_sent' => (int) $parts[3],
                        'connected_since' => $this->parseDate($parts[4]),
                        'virtual_address' => null,
                    ];
                }
            }

            if ($section === 'routes' && ! empty($line)) {
                $parts = str_getcsv($line);
                if (count($parts) >= 2 && ! empty($parts[1])) {
                    $routes[$parts[1]] = $parts[0]; // common_name => virtual_address
                }
            }
        }

        // Merge virtual addresses into clients
        foreach ($routes as $commonName => $virtualAddress) {
            if (isset($clients[$commonName])) {
                $clients[$commonName]['virtual_address'] = $virtualAddress;
            }
        }

        return array_values($clients);
    }

    private function parseDate(string $date): ?Carbon
    {
        try {
            return Carbon::parse($date);
        } catch (\Exception) {
            return null;
        }
    }
}
