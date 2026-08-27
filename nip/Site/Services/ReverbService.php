<?php

namespace Nip\Site\Services;

use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Enums\StopSignal;
use Nip\BackgroundProcess\Jobs\RemoveBackgroundProcessJob;
use Nip\BackgroundProcess\Jobs\SyncBackgroundProcessJob;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Site\Models\Site;
use RuntimeException;

class ReverbService
{
    public const REVERB_DAEMON_NAME = 'Reverb';

    public const PORT_RANGE_START = 8080;

    public const PORT_RANGE_END = 8099;

    public function enable(Site $site): BackgroundProcess
    {
        $existingProcess = $this->getReverbProcess($site);

        if ($existingProcess) {
            return $existingProcess;
        }

        $port = $this->allocatePort($site);
        $phpVersion = $site->php_version?->version();

        $process = BackgroundProcess::create([
            'server_id' => $site->server_id,
            'site_id' => $site->id,
            'name' => self::REVERB_DAEMON_NAME,
            'command' => "php{$phpVersion} artisan reverb:start --no-interaction --port={$port}",
            'directory' => $site->getApplicationPath(),
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
        $process = $this->getReverbProcess($site);

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
        return $this->getReverbProcess($site) !== null;
    }

    /**
     * Matched by command rather than by name so the badge, the stop button and
     * a process someone created by hand all agree on what "enabled" means.
     */
    public function getReverbProcess(Site $site): ?BackgroundProcess
    {
        return $site->backgroundProcesses()
            ->where('command', 'like', '%reverb:start%')
            ->whereNotIn('status', [ProcessStatus::Deleting, ProcessStatus::Failed])
            ->first();
    }

    public function portOf(BackgroundProcess $process): ?int
    {
        return preg_match('/--port=(\d+)/', $process->command, $matches)
            ? (int) $matches[1]
            : null;
    }

    /**
     * Reverb binds a loopback port that nginx proxies the public name to, so
     * every daemon on one machine needs its own.
     *
     * Only ports this platform handed out are visible here: a daemon that keeps
     * its port in its own configuration (the nipgate tunnel holds 8085-8087) is
     * invisible, and colliding with one shows up as a supervisor FATAL.
     */
    public function allocatePort(Site $site): int
    {
        $taken = $this->portsInUseOn($site->server_id);

        for ($port = self::PORT_RANGE_START; $port <= self::PORT_RANGE_END; $port++) {
            if (! in_array($port, $taken, true)) {
                return $port;
            }
        }

        throw new RuntimeException(
            'No free port between '.self::PORT_RANGE_START.' and '.self::PORT_RANGE_END.' on this server.'
        );
    }

    /**
     * @return array<int>
     */
    protected function portsInUseOn(int $serverId): array
    {
        return BackgroundProcess::query()
            ->where('server_id', $serverId)
            ->pluck('command')
            // A process being removed still holds its port until the supervisor
            // program is actually gone, so every status counts here.
            ->map(fn (?string $command): ?int => $command && preg_match('/--port=(\d+)/', $command, $matches)
                ? (int) $matches[1]
                : null)
            ->filter()
            ->values()
            ->all();
    }
}
