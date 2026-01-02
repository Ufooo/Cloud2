<?php

namespace Nip\Server\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Server\Services\SSH\SSHService;

/**
 * Base class for simple one-liner SSH commands.
 *
 * Use this instead of BaseProvisionJob when:
 * - The command is a single line (no multi-step script needed)
 * - No audit trail / ProvisionScript record is needed
 * - Examples: supervisorctl, update-alternatives, simple mysql commands
 */
abstract class BaseCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 2;

    public int $timeout = 120;

    /** @var array<int> */
    public array $backoff = [10, 30, 60];

    abstract protected function getServer(): Server;

    abstract protected function getCommand(): string;

    abstract protected function handleSuccess(ExecutionResult $result): void;

    protected function getRunAsUser(): ?string
    {
        return null;
    }

    protected function getSshTimeout(): int
    {
        return 30;
    }

    public function handle(SSHService $ssh): void
    {
        $server = $this->getServer();
        $runAsUser = $this->getRunAsUser();

        try {
            $ssh->setTimeout($this->getSshTimeout());
            $ssh->connect($server, $runAsUser);

            $result = $ssh->exec($this->getCommand());

            if ($result->isSuccessful()) {
                $this->handleSuccess($result);
            } else {
                throw new Exception("Command failed with exit code {$result->exitCode}: {$result->output}");
            }

        } catch (Exception $e) {
            if ($this->shouldRetry($e)) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
            } else {
                $this->fail($e);
            }
        } finally {
            $ssh->disconnect();
        }
    }

    protected function shouldRetry(Exception $e): bool
    {
        if (str_contains($e->getMessage(), 'SSH') || str_contains($e->getMessage(), 'connection')) {
            return $this->attempts() < $this->tries;
        }

        return $this->attempts() < $this->tries - 1;
    }

    public function failed(\Throwable $exception): void
    {
        $this->handleFailure($exception);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Override in child classes
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'command',
            'server:'.$this->getServer()->id,
        ];
    }
}
