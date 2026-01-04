<?php

namespace Nip\Server\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nip\Server\Enums\ProvisionScriptStatus;
use Nip\Server\Models\ProvisionScript;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Server\Services\SSH\SSHService;

abstract class BaseProvisionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 2;

    public int $timeout = 3600;

    /** @var array<int> */
    public array $backoff = [60, 180, 600];

    protected ?ProvisionScript $script = null;

    abstract protected function getResourceType(): string;

    abstract protected function getResourceId(): ?int;

    abstract protected function getServer(): Server;

    abstract protected function generateScript(): string;

    abstract protected function handleSuccess(ExecutionResult $result): void;

    protected function getRunAsUser(): ?string
    {
        return null;
    }

    /**
     * Called during script execution with streaming output.
     * Override in child classes to handle real-time output updates.
     */
    protected function onOutputReceived(string $chunk, string $fullOutput): void
    {
        // Override in child classes for real-time updates
    }

    /**
     * Whether to use streaming output for this job.
     */
    protected function useStreamingOutput(): bool
    {
        return false;
    }

    protected function getSshTimeout(): int
    {
        // Default to 10 minutes for SSH operations, can be overridden in child classes
        return 600;
    }

    public function handle(SSHService $ssh): void
    {
        $server = $this->getServer();
        $runAsUser = $this->getRunAsUser();

        try {
            $this->createProvisionScript();

            // Set SSH timeout to match job timeout for long-running scripts
            $ssh->setTimeout($this->getSshTimeout());
            $ssh->connect($server, $runAsUser);

            $outputCallback = $this->useStreamingOutput()
                ? fn (string $chunk, string $fullOutput) => $this->onOutputReceived($chunk, $fullOutput)
                : null;

            $result = $ssh->executeScript($this->script->content, $outputCallback);

            $this->script->update([
                'output' => $result->output,
                'exit_code' => $result->exitCode,
                'executed_at' => now(),
                'status' => $result->isSuccessful() ? ProvisionScriptStatus::Completed : ProvisionScriptStatus::Failed,
            ]);

            if ($result->isSuccessful()) {
                $this->handleSuccess($result);
            } else {
                throw new Exception("Script execution failed with exit code {$result->exitCode}");
            }

        } catch (Exception $e) {
            if ($this->shouldRetry($e)) {
                if ($this->script) {
                    $this->script->update([
                        'output' => $this->script->output."\n\n[RETRY] ".$e->getMessage(),
                    ]);
                }
                $this->release($this->backoff[$this->attempts() - 1] ?? 600);
            } else {
                $this->fail($e);
            }
        } finally {
            $ssh->disconnect();
        }
    }

    protected function createProvisionScript(): void
    {
        $server = $this->getServer();
        // Format: YYYYMMDD_HHMMSS_microseconds (like Forge: 20260103_144248_96052)
        $scriptId = now()->format('Ymd_His').'_'.substr((string) hrtime(true), -6);
        $filename = "provision-{$scriptId}.sh";

        $originalScript = $this->generateScript();
        $wrappedScript = $this->wrapScript($originalScript, $scriptId);

        $this->script = ProvisionScript::create([
            'server_id' => $server->id,
            'filename' => $filename,
            'resource_type' => $this->getResourceType(),
            'resource_id' => $this->getResourceId(),
            'run_as_user' => $this->getRunAsUser(),
            'content' => $wrappedScript,
            'status' => ProvisionScriptStatus::Executing,
        ]);
    }

    /**
     * Wrap the script with logging and audit trail functionality.
     * Similar to Forge's approach - saves script to file and logs output.
     */
    protected function wrapScript(string $script, string $scriptId): string
    {
        $runAsUser = $this->getRunAsUser();
        $netiparDir = $runAsUser ? "/home/{$runAsUser}/.netipar" : '/root/.netipar';
        $scriptFile = "{$netiparDir}/provision-{$scriptId}.sh";
        $outputFile = "{$netiparDir}/provision-{$scriptId}.output";

        return <<<BASH
#!/bin/bash

mkdir -p "{$netiparDir}"

cat > "{$scriptFile}" << 'NETIPAR_SCRIPT_{$scriptId}'
{$script}
NETIPAR_SCRIPT_{$scriptId}

chmod +x "{$scriptFile}"

bash "{$scriptFile}" 2>&1 | tee "{$outputFile}"
NETIPAR_STATUS=\${PIPESTATUS[0]}

# Clean up old provision files (keep last 50)
cd "{$netiparDir}" && ls -t provision-*.sh 2>/dev/null | tail -n +51 | xargs -r rm -f
cd "{$netiparDir}" && ls -t provision-*.output 2>/dev/null | tail -n +51 | xargs -r rm -f

exit \$NETIPAR_STATUS
BASH;
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
        if ($this->script) {
            $this->script->update([
                'status' => ProvisionScriptStatus::Failed,
                'output' => $this->script->output."\n\n[FATAL ERROR] ".$exception->getMessage(),
            ]);
        }

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
            'provision',
            $this->getResourceType(),
            'server:'.$this->getServer()->id,
        ];
    }
}
