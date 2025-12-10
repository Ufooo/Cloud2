<?php

namespace Nip\Server\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Events\ServerProvisioningUpdated;
use Nip\Server\Models\Server;

class ProvisioningController extends Controller
{
    /**
     * Serve the provisioning script for a server.
     */
    public function script(Server $server, Request $request): Response
    {
        abort_unless($request->query('token') === $server->provisioning_token, 403);

        $callbackUrl = route('provisioning.callback');
        $script = $this->generateProvisioningScript($server, $callbackUrl);

        return response($script, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Handle provisioning status callback from server.
     */
    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'integer'],
            'status' => ['required', 'integer'],
        ]);

        $server = Server::query()
            ->where('id', $validated['server_id'])
            ->where('provisioning_token', $request->header('Authorization'))
            ->firstOrFail();

        $server->update([
            'provision_step' => $validated['status'],
        ]);

        // Mark as ready when provisioning completes (last step)
        if ($validated['status'] >= 10) {
            $server->update([
                'status' => ServerStatus::Connected,
                'is_ready' => true,
            ]);
        }

        broadcast(new ServerProvisioningUpdated($server));

        return response()->json(['status' => 'ok']);
    }

    /**
     * Generate the provisioning bash script.
     */
    private function generateProvisioningScript(Server $server, string $callbackUrl): string
    {
        $serverId = $server->id;
        $token = $server->provisioning_token;

        return <<<BASH
#!/bin/bash

# Cloud Provisioning Script
# Server: {$server->name} (ID: {$serverId})

export DEBIAN_FRONTEND=noninteractive

function provisionPing {
    curl --silent --insecure \\
        --header "Authorization: {$token}" \\
        --data "status=\$1&server_id={$serverId}" \\
        {$callbackUrl}
}

echo "Starting provisioning..."

# Step 1: Server is ready
provisionPing 1

# Step 2: Preparing server
echo "Preparing server..."
provisionPing 2

# Step 3: Configuring swap
echo "Configuring swap..."
provisionPing 3

# Step 4: Installing base dependencies
echo "Installing base dependencies..."
apt-get update
apt-get install -y curl wget git unzip
provisionPing 4

# Step 5: Installing PHP
echo "Installing PHP {$server->php_version}..."
provisionPing 5

# Step 6: Installing Nginx
echo "Installing Nginx..."
provisionPing 6

# Step 7: Installing Database
echo "Installing database..."
provisionPing 7

# Step 8: Installing Redis
echo "Installing Redis..."
provisionPing 8

# Step 9: Making final touches
echo "Making final touches..."
provisionPing 9

# Step 10: Done
echo "Provisioning complete!"
provisionPing 10

BASH;
    }
}
