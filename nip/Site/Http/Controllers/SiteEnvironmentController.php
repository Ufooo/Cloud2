<?php

namespace Nip\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Data\SiteData;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Http\Requests\UpdateEnvironmentRequest;
use Nip\Site\Models\Site;

class SiteEnvironmentController extends Controller
{
    public function show(Site $site): Response
    {
        Gate::authorize('update', $site->server);

        abort_unless(
            $site->status === SiteStatus::Installed,
            403,
            'Site must be installed to manage environment variables.'
        );

        return Inertia::render('sites/Environment', [
            'site' => SiteData::fromModel($site),
        ]);
    }

    public function get(Site $site, SSHService $ssh): JsonResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless(
            $site->status === SiteStatus::Installed,
            403,
            'Site must be installed to view environment variables.'
        );

        $envPath = $this->getEnvPath($site);

        $ssh->connect($site->server, $site->user);
        $content = $ssh->getFileContent($envPath);
        $ssh->disconnect();

        if ($content === null) {
            return response()->json([
                'success' => false,
                'error' => 'Could not read environment file. The file may not exist.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'content' => $content,
        ]);
    }

    public function update(UpdateEnvironmentRequest $request, Site $site, SSHService $ssh): JsonResponse
    {
        $validated = $request->validated();

        // Always save switch preferences to database
        $site->update([
            'env_clear_config_cache' => $validated['clear_config_cache'] ?? false,
            'env_restart_queue' => $validated['restart_queue'] ?? false,
        ]);

        // Only upload env file if content was provided
        $commandOutput = '';
        if (! empty($validated['environment'])) {
            $envPath = $this->getEnvPath($site);

            $ssh->connect($site->server, $site->user);

            // Write the environment file
            $ssh->uploadContent($validated['environment'], $envPath);

            // Run artisan commands if enabled
            $artisanCommands = [];
            $applicationPath = $site->getApplicationPath();

            if ($validated['clear_config_cache'] ?? false) {
                $artisanCommands[] = 'config:cache';
            }

            if ($validated['restart_queue'] ?? false) {
                $artisanCommands[] = 'queue:restart';
            }

            foreach ($artisanCommands as $command) {
                $result = $ssh->exec("cd {$applicationPath} && php artisan {$command} 2>&1");
                $commandOutput .= "php artisan {$command}:\n{$result->output}\n";
            }

            $ssh->disconnect();
        }

        return response()->json([
            'success' => true,
            'message' => empty($validated['environment'])
                ? 'Settings saved successfully.'
                : 'Environment file updated successfully.',
            'commands_output' => $commandOutput,
        ]);
    }

    private function getEnvPath(Site $site): string
    {
        // For zero-downtime: .env lives at siteRoot (shared, symlinked to releases)
        // For standard: .env lives at applicationPath
        if ($site->zero_downtime) {
            return $site->getSiteRoot().'/.env';
        }

        return $site->getApplicationPath().'/.env';
    }
}
