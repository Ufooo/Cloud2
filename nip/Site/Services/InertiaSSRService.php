<?php

namespace Nip\Site\Services;

use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Enums\StopSignal;
use Nip\BackgroundProcess\Jobs\RemoveBackgroundProcessJob;
use Nip\BackgroundProcess\Jobs\SyncBackgroundProcessJob;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Site\Models\Site;

class InertiaSSRService
{
    public const SSR_DAEMON_NAME = 'Inertia SSR';

    public function enable(Site $site): BackgroundProcess
    {
        $existingProcess = $this->getSSRProcess($site);

        if ($existingProcess) {
            return $existingProcess;
        }

        $phpVersion = $site->php_version?->version();
        $command = "php{$phpVersion} artisan inertia:start-ssr";

        $process = BackgroundProcess::create([
            'server_id' => $site->server_id,
            'site_id' => $site->id,
            'name' => self::SSR_DAEMON_NAME,
            'command' => $command,
            'directory' => $site->getCurrentPath(),
            'user' => $site->user,
            'processes' => 1,
            'startsecs' => 1,
            'stopwaitsecs' => 5,
            'stopsignal' => StopSignal::TERM,
            'status' => ProcessStatus::Installing,
        ]);

        SyncBackgroundProcessJob::dispatch($process);

        return $process;
    }

    public function disable(Site $site): void
    {
        $process = $this->getSSRProcess($site);

        if (! $process) {
            return;
        }

        $process->update([
            'status' => ProcessStatus::Deleting,
        ]);

        RemoveBackgroundProcessJob::dispatch($process);
    }

    public function isEnabled(Site $site): bool
    {
        return $this->getSSRProcess($site) !== null;
    }

    public function getSSRProcess(Site $site): ?BackgroundProcess
    {
        return $site->backgroundProcesses()
            ->where('name', self::SSR_DAEMON_NAME)
            ->whereNotIn('status', [ProcessStatus::Deleting, ProcessStatus::Failed])
            ->first();
    }
}
