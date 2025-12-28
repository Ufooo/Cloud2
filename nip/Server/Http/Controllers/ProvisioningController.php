<?php

namespace Nip\Server\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Models\Database;
use Nip\Network\Enums\RuleStatus;
use Nip\Network\Enums\RuleType;
use Nip\Network\Models\FirewallRule;
use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Enums\ServerType;
use Nip\Server\Events\ServerProvisioningUpdated;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Models\Server;
use Nip\SshKey\Enums\SshKeyStatus;
use Nip\SshKey\Models\SshKey;
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

            // Mark all unix users as installed
            UnixUser::query()
                ->where('server_id', $server->id)
                ->where('status', UserStatus::Installing)
                ->update(['status' => UserStatus::Installed]);

            ServerResourceStatusUpdated::dispatch($server, 'unix_user', null, UserStatus::Installed->value);

            // Mark all PHP versions as installed
            PhpVersion::query()
                ->where('server_id', $server->id)
                ->where('status', PhpVersionStatus::Installing)
                ->update(['status' => PhpVersionStatus::Installed]);

            ServerResourceStatusUpdated::dispatch($server, 'php_version', null, PhpVersionStatus::Installed->value);

            // Mark all SSH keys as installed
            SshKey::query()
                ->where('server_id', $server->id)
                ->where('status', SshKeyStatus::Pending)
                ->update(['status' => SshKeyStatus::Installed]);

            ServerResourceStatusUpdated::dispatch($server, 'ssh_key', null, SshKeyStatus::Installed->value);

            // Create default firewall rules in database
            $this->createDefaultFirewallRules($server);

            // Create default netipar database record if server has database
            if ($server->database_type !== null) {
                $this->createDefaultDatabase($server);
            }
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
        $phpVersion = $server->phpVersions()->where('is_cli_default', true)->first()?->version ?? '8.4';
        $sudoPassword = Str::random(20);
        $databasePassword = $server->database_password ?? Str::random(20);
        $meilisearchKey = Str::random(20);
        $eventId = time();

        // Store database password if not already set
        if (! $server->database_password && $server->database_type) {
            $server->update(['database_password' => $databasePassword]);
        }

        return [
            'server' => $server,
            'callbackUrl' => $this->getCallbackUrl(),
            'rootSshKeys' => $this->formatSshKeysForUser($server, 'root'),
            'netiparSshKeys' => $this->formatSshKeysForUser($server, 'netipar'),
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

    /**
     * Create the default netipar database record.
     */
    private function createDefaultDatabase(Server $server): void
    {
        Database::firstOrCreate(
            [
                'server_id' => $server->id,
                'name' => 'netipar',
            ],
            [
                'status' => DatabaseStatus::Installed,
            ]
        );
    }

    /**
     * Create default firewall rules in the database.
     */
    private function createDefaultFirewallRules(Server $server): void
    {
        $defaultRules = [
            ['name' => 'SSH', 'port' => '22'],
            ['name' => 'HTTP', 'port' => '80'],
            ['name' => 'HTTPS', 'port' => '443'],
        ];

        foreach ($defaultRules as $rule) {
            FirewallRule::firstOrCreate(
                [
                    'server_id' => $server->id,
                    'port' => $rule['port'],
                ],
                [
                    'name' => $rule['name'],
                    'type' => RuleType::Allow,
                    'status' => RuleStatus::Installed,
                ]
            );
        }
    }

    /**
     * Format SSH keys for a specific unix user with identifying comments.
     */
    private function formatSshKeysForUser(Server $server, string $username): string
    {
        $lines = [];

        // Server's own key always goes to all users
        if ($server->ssh_public_key) {
            $lines[] = '# Netipar[server]: Cloud Management Key';
            $lines[] = $server->ssh_public_key;
        }

        // User-specific SSH keys
        $userSshKeys = $server->sshKeys()
            ->whereHas('unixUser', fn ($query) => $query->where('username', $username))
            ->get();

        foreach ($userSshKeys as $sshKey) {
            $lines[] = "# Netipar[{$sshKey->id}]: {$sshKey->name}";
            $lines[] = $sshKey->public_key;
        }

        return implode("\n", $lines);
    }
}
