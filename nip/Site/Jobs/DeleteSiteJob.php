<?php

namespace Nip\Site\Jobs;

use Illuminate\Support\Facades\Log;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Models\Site;

class DeleteSiteJob extends BaseProvisionJob
{
    public function __construct(
        public Site $site,
        public bool $deleteDatabase = false,
        public bool $deleteDatabaseUser = false,
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
            ->forSameUserOnServer($this->site)
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
            'siteRoot' => $this->site->getSiteRoot(),
            'phpVersion' => $this->site->php_version?->version(),
            'shouldDeletePool' => ! $otherSitesWithSameUser,
            'installedPhpVersions' => $this->site->server->phpVersions->pluck('version')->toArray(),
            'domainRecords' => $this->site->domainRecords->pluck('name')->toArray(),
            'scheduledJobs' => $scheduledJobs,
            'backgroundProcesses' => $backgroundProcesses,
            'deleteDatabase' => $this->deleteDatabase,
            'deleteDatabaseUser' => $this->deleteDatabaseUser,
            'databaseName' => $this->site->database?->name,
            'databaseUserName' => $this->site->databaseUser?->username,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        // Delete database record if it was deleted on the server
        if ($this->deleteDatabase && $this->site->database) {
            $this->site->database->delete();
        }

        // Delete database user record if it was deleted on the server
        if ($this->deleteDatabaseUser && $this->site->databaseUser) {
            $this->site->databaseUser->delete();
        }

        $this->site->delete();
    }

    protected function handleFailure(\Throwable $exception): void
    {
        Log::error('Failed to delete site', [
            'site_id' => $this->site->id,
            'site_domain' => $this->site->domain,
            'server_id' => $this->site->server_id,
            'error' => $exception->getMessage(),
        ]);
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
