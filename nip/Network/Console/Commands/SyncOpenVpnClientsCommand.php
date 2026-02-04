<?php

namespace Nip\Network\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Nip\Network\Models\OpenVpnClient;

class SyncOpenVpnClientsCommand extends Command
{
    protected $signature = 'openvpn:sync';

    protected $description = 'Sync OpenVPN clients from status JSON file';

    public function handle(): int
    {
        $jsonPath = 'openvpn/clients.json';

        if (! Storage::exists($jsonPath)) {
            $this->error("JSON file not found: {$jsonPath}");

            return self::FAILURE;
        }

        $clients = json_decode(Storage::get($jsonPath), true);

        if (! is_array($clients)) {
            $this->error('Invalid JSON format');

            return self::FAILURE;
        }

        // Mark all clients as offline first
        OpenVpnClient::query()->update(['is_online' => false]);

        $synced = 0;
        foreach ($clients as $client) {
            if (empty($client['common_name'])) {
                continue;
            }

            OpenVpnClient::updateOrCreate(
                ['common_name' => $client['common_name']],
                [
                    'real_address' => $client['real_address'] ?? null,
                    'virtual_address' => $client['virtual_address'] ?? null,
                    'bytes_received' => $client['bytes_received'] ?? 0,
                    'bytes_sent' => $client['bytes_sent'] ?? 0,
                    'connected_since' => $client['connected_since'] ?? null,
                    'is_online' => true,
                    'last_seen_at' => now(),
                ]
            );
            $synced++;
        }

        $this->info("Synced {$synced} OpenVPN clients");

        return self::SUCCESS;
    }
}
