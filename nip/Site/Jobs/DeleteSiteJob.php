<?php

namespace Nip\Site\Jobs;

use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Models\Site;

class DeleteSiteJob extends BaseProvisionJob
{
    public function __construct(
        public Site $site,
    ) {}

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

    protected function generateScript(): string
    {
        // Check if any other sites on this server use the same user
        $otherSitesWithSameUser = Site::query()
            ->where('server_id', $this->site->server_id)
            ->where('user', $this->site->user)
            ->whereNot('id', $this->site->id)
            ->exists();

        // Get scheduled jobs for this site (to remove cron entries)
        $scheduledJobs = $this->site->scheduledJobs()
            ->select(['id', 'user'])
            ->get()
            ->map(fn ($job) => ['id' => $job->id, 'user' => $job->user])
            ->toArray();

        // Get background processes for this site (to remove supervisor configs)
        $backgroundProcesses = $this->site->backgroundProcesses()
            ->select(['id'])
            ->pluck('id')
            ->toArray();

        return view('provisioning.scripts.site.delete', [
            'site' => $this->site,
            'user' => $this->site->user,
            'domain' => $this->site->domain,
            'fullPath' => $this->site->getFullPath(),
            'phpVersion' => $this->site->getEffectivePhpVersion(),
            'shouldDeletePool' => ! $otherSitesWithSameUser,
            'installedPhpVersions' => $this->site->server->phpVersions->pluck('version')->toArray(),
            'domainRecords' => $this->site->domainRecords->pluck('name')->toArray(),
            'scheduledJobs' => $scheduledJobs,
            'backgroundProcesses' => $backgroundProcesses,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->site->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Keep the site in deleting status for manual intervention
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
            'delete',
        ];
    }
}
