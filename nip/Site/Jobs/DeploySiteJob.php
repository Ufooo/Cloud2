<?php

namespace Nip\Site\Jobs;

use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Deployment\Events\DeploymentUpdated;
use Nip\Deployment\Models\Deployment;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Events\SiteStatusUpdated;
use Nip\Site\Models\Site;

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

        // Broadcast the update
        DeploymentUpdated::dispatch($this->deployment);
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
        return view('provisioning.scripts.site.deploy-wrapper', [
            'site' => $this->site,
            'user' => $this->site->user,
            'domain' => $this->site->domain,
            'fullPath' => $this->site->getFullPath(),
            'currentPath' => $this->site->getCurrentPath(),
            'branch' => $this->site->branch ?? 'main',
            'phpVersion' => $this->site->getEffectivePhpVersion(),
            'deployScriptContent' => $deployScriptContent,
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

        DeploymentUpdated::dispatch($this->deployment);
        SiteStatusUpdated::dispatch($this->site);
    }

    protected function handleFailure(\Throwable $exception): void
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

        DeploymentUpdated::dispatch($this->deployment);
        SiteStatusUpdated::dispatch($this->site);
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
}
