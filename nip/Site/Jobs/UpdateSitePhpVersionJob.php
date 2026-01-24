<?php

namespace Nip\Site\Jobs;

use Illuminate\Support\Facades\Log;
use Nip\BackgroundProcess\Jobs\SyncBackgroundProcessJob;
use Nip\Scheduler\Jobs\SyncScheduledJobJob;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Shared\Traits\ResolvesPhpVersion;
use Nip\Site\Models\Site;

class UpdateSitePhpVersionJob extends BaseProvisionJob
{
    use ResolvesPhpVersion;

    public int $timeout = 120;

    public $queue = 'provisioning';

    public function __construct(
        public Site $site,
        public string $newVersion
    ) {}

    protected function getResourceType(): string
    {
        return 'site_php_version';
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
        $domainRecords = $this->site->domainRecords()
            ->where('name', '!=', $this->site->domain)
            ->pluck('name')
            ->toArray();

        return view('provisioning.scripts.site.steps.update-php-version', [
            'site' => $this->site,
            'domain' => $this->site->domain,
            'domainRecords' => $domainRecords,
            'oldVersion' => $this->site->php_version?->version(),
            'newVersion' => $this->resolvePhpVersionString($this->newVersion),
            'user' => $this->site->user,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $oldVersion = $this->site->php_version?->version();
        $newVersion = $this->resolvePhpVersionString($this->newVersion);

        $this->site->update([
            'php_version' => $this->newVersion,
        ]);

        // Update scheduled jobs that use PHP
        $this->updateScheduledJobs($oldVersion, $newVersion);

        // Update background processes that use PHP
        $this->updateBackgroundProcesses($oldVersion, $newVersion);
    }

    protected function updateScheduledJobs(?string $oldVersion, string $newVersion): void
    {
        if (! $oldVersion) {
            return;
        }

        $scheduledJobs = $this->site->scheduledJobs()
            ->where('command', 'like', "%php{$oldVersion}%")
            ->get();

        foreach ($scheduledJobs as $job) {
            $job->update([
                'command' => str_replace("php{$oldVersion}", "php{$newVersion}", $job->command),
            ]);

            SyncScheduledJobJob::dispatch($job);
        }
    }

    protected function updateBackgroundProcesses(?string $oldVersion, string $newVersion): void
    {
        if (! $oldVersion) {
            return;
        }

        $processes = $this->site->backgroundProcesses()
            ->where('command', 'like', "%php{$oldVersion}%")
            ->get();

        foreach ($processes as $process) {
            $process->update([
                'command' => str_replace("php{$oldVersion}", "php{$newVersion}", $process->command),
            ]);

            SyncBackgroundProcessJob::dispatch($process);
        }
    }

    protected function handleFailure(\Throwable $exception): void
    {
        Log::error('Failed to update PHP version for site', [
            'site_id' => $this->site->id,
            'site_domain' => $this->site->domain,
            'old_version' => $this->site->php_version,
            'new_version' => $this->newVersion,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'site_php_version',
            'site:'.$this->site->id,
            'server:'.$this->site->server_id,
        ];
    }
}
