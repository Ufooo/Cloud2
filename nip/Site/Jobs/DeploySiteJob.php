<?php

namespace Nip\Site\Jobs;

use Illuminate\Support\Facades\Log;
use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Deployment\Events\DeploymentUpdated;
use Nip\Deployment\Models\Deployment;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Events\SiteStatusUpdated;
use Nip\Site\Models\Site;
use Throwable;

class DeploySiteJob extends BaseProvisionJob
{
    private const BROADCAST_THROTTLE_MS = 500;

    public int $tries = 1;

    private int $lastBroadcastAt = 0;

    public function __construct(
        public Site $site,
        public Deployment $deployment,
    ) {}

    protected function useStreamingOutput(): bool
    {
        return true;
    }

    protected function onOutputReceived(string $chunk, string $fullOutput): void
    {
        // Throttle broadcasts to avoid overwhelming the WebSocket
        $now = (int) (microtime(true) * 1000);
        if ($now - $this->lastBroadcastAt < self::BROADCAST_THROTTLE_MS) {
            return;
        }
        $this->lastBroadcastAt = $now;

        // Update deployment output in database
        $this->deployment->update(['output' => $fullOutput]);

        // Broadcast the update (safely, in case Reverb is unavailable)
        try {
            DeploymentUpdated::dispatch($this->deployment);
        } catch (Throwable) {
            // Silently ignore - Reverb may be unavailable during self-deployment
        }
    }

    protected function getResourceType(): string
    {
        return 'site';
    }

    protected function getResourceId(): ?int
    {
        return $this->site->id;
    }

    protected function getServer(): Server
    {
        return $this->site->server;
    }

    protected function getRunAsUser(): ?string
    {
        return $this->site->user;
    }

    protected function generateScript(): string
    {
        // Use site's custom deploy script if set, otherwise use the default for the site type
        $deployScriptContent = $this->site->deploy_script ?? $this->site->type->defaultDeployScript();

        // Wrap the deploy script with environment setup and error handling
        $callbackUrl = $this->deployment->callback_token
            ? route('deploy.callback', ['token' => $this->deployment->callback_token])
            : null;

        return view('provisioning.scripts.site.deploy-wrapper', [
            'site' => $this->site,
            'deployment' => $this->deployment,
            'user' => $this->site->user,
            'domain' => $this->site->domain,
            'fullPath' => $this->site->getFullPath(),
            'currentPath' => $this->site->getCurrentPath(),
            'branch' => $this->site->branch ?? 'main',
            'phpVersion' => $this->site->php_version?->version(),
            'deployScriptContent' => $deployScriptContent,
            'callbackUrl' => $callbackUrl,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->deployment->update([
            'status' => DeploymentStatus::Finished,
            'output' => $result->output,
            'ended_at' => now(),
        ]);

        $this->site->update([
            'deploy_status' => DeployStatus::Deployed,
            'last_deployed_at' => now(),
        ]);

        $this->safeBroadcast();
    }

    protected function handleFailure(Throwable $exception): void
    {
        // Get the actual script output if available
        $output = $this->script?->output ?? $this->deployment->output ?? '';

        $this->deployment->update([
            'status' => DeploymentStatus::Failed,
            'output' => $output."\n\n[ERROR] ".$exception->getMessage(),
            'ended_at' => now(),
        ]);

        $this->site->update([
            'deploy_status' => DeployStatus::Failed,
        ]);

        $this->safeBroadcast();
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'provision',
            'site',
            'site:'.$this->site->id,
            'server:'.$this->site->server_id,
            'deploy',
        ];
    }

    /**
     * Safely broadcast deployment and site status updates.
     * Catches broadcast errors to prevent deployment failures when Reverb is unavailable
     * (e.g., when deploying the Cloud application to itself).
     */
    private function safeBroadcast(): void
    {
        try {
            DeploymentUpdated::dispatch($this->deployment);
            SiteStatusUpdated::dispatch($this->site);
        } catch (Throwable $e) {
            Log::warning('Failed to broadcast deployment update (Reverb may be unavailable)', [
                'deployment_id' => $this->deployment->id,
                'site_id' => $this->site->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
