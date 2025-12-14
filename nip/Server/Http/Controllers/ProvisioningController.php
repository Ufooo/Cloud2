<?php

namespace Nip\Server\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Enums\ServerType;
use Nip\Server\Events\ServerProvisioningUpdated;
use Nip\Server\Models\Server;
use Nip\UnixUser\Enums\UserStatus;
use Nip\UnixUser\Models\UnixUser;

class ProvisioningController extends Controller
{
    /**
     * Serve the provisioning script for a server.
     */
    public function script(Server $server, Request $request): Response
    {
        abort_unless($request->query('token') === $server->provisioning_token, 403);

        $script = $this->renderProvisioningScript($server);

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

        $server = Server::query()->findOrFail($validated['server_id']);

        $server->update([
            'provision_step' => $validated['status'],
        ]);

        // Create unix user when status >= 2 (after PreparingServer step)
        if ($validated['status'] >= 2) {
            UnixUser::query()->firstOrCreate(
                [
                    'server_id' => $server->id,
                    'username' => 'netipar',
                ],
                [
                    'status' => UserStatus::Installed,
                ]
            );
        }

        // Create PHP version when status >= 5 (after InstallingPhp step)
        if ($validated['status'] >= 5) {
            $phpVersion = $server->php_version ? str_replace('php', '', $server->php_version) : '8.4';
            PhpVersion::query()->firstOrCreate(
                [
                    'server_id' => $server->id,
                    'version' => $phpVersion,
                ],
                [
                    'is_cli_default' => true,
                    'is_site_default' => true,
                    'status' => PhpVersionStatus::Installed,
                ]
            );
        }

        // Mark database as installed when status >= 7 (after InstallingDatabase step)
        if ($validated['status'] >= 7 && $server->database_type !== null) {
            $server->update(['db_status' => 'installed']);
        }

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
     * Render the provisioning script using Blade templates.
     */
    private function renderProvisioningScript(Server $server): string
    {
        $viewName = $this->getViewName($server->type);
        $variables = $this->getTemplateVariables($server);

        return view($viewName, $variables)->render();
    }

    /**
     * Get the Blade view name based on server type.
     */
    private function getViewName(ServerType $type): string
    {
        return match ($type) {
            ServerType::App => 'provisioning.scripts.app',
            ServerType::Web => 'provisioning.scripts.web',
            ServerType::Worker => 'provisioning.scripts.worker',
            ServerType::Database => 'provisioning.scripts.database',
            ServerType::Cache => 'provisioning.scripts.cache',
            ServerType::LoadBalancer => 'provisioning.scripts.loadbalancer',
            ServerType::Meilisearch => 'provisioning.scripts.meilisearch',
        };
    }

    /**
     * Get all template variables for the provisioning script.
     */
    private function getTemplateVariables(Server $server): array
    {
        $sshPublicKey = $server->sshKeys()->pluck('public_key')->implode("\n");
        $phpVersion = $server->php_version ? str_replace('php', '', $server->php_version) : '8.4';
        $sudoPassword = Str::random(20);
        $databasePassword = Str::random(20);
        $meilisearchKey = Str::random(20);
        $eventId = time();

        return [
            'server' => $server,
            'callbackUrl' => $this->getCallbackUrl(),
            'sshPublicKey' => $sshPublicKey,
            'phpVersion' => $phpVersion,
            'sudoPassword' => $sudoPassword,
            'databasePassword' => $databasePassword,
            'mariadbVersion' => $server->database_type?->version() ?? '11.4',
            'postgresqlVersion' => $server->database_type?->version() ?? '16',
            'nodeVersion' => 22,
            'meilisearchKey' => $meilisearchKey,
            'eventId' => $eventId,
        ];
    }

    /**
     * Get the callback URL for provisioning status updates.
     */
    private function getCallbackUrl(): string
    {
        return rtrim(config('app.url'), '/').'/provisioning/callback/status';
    }
}
