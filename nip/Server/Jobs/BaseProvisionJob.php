<?php

namespace Nip\Server\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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

    public function handle(SSHService $ssh): void
    {
        $server = $this->getServer();
        $runAsUser = $this->getRunAsUser();

        try {
            Log::info("Starting provision job for {$this->getResourceType()} on server {$server->id}".($runAsUser ? " as {$runAsUser}" : ''));

            $this->createProvisionScript();

            $ssh->connect($server, $runAsUser);

            $result = $ssh->executeScript($this->script->content);

            $this->script->update([
                'output' => $result->output,
                'exit_code' => $result->exitCode,
                'executed_at' => now(),
                'status' => $result->isSuccessful() ? ProvisionScriptStatus::Completed : ProvisionScriptStatus::Failed,
            ]);

            if ($result->isSuccessful()) {
                Log::info("Provision job completed successfully for {$this->getResourceType()} on server {$server->id}");
                $this->handleSuccess($result);
            } else {
                throw new Exception("Script execution failed with exit code {$result->exitCode}");
            }

        } catch (Exception $e) {
            Log::error("Provision job failed for {$this->getResourceType()}: ".$e->getMessage());

            if ($this->script) {
                $this->script->update([
                    'status' => ProvisionScriptStatus::Failed,
                    'output' => $this->script->output."\n\n[ERROR] ".$e->getMessage(),
                ]);
            }

            if ($this->shouldRetry($e)) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 600);
            } else {
                $this->handleFailure($e);
                $this->fail($e);
            }
        } finally {
            $ssh->disconnect();
        }
    }

    protected function createProvisionScript(): void
    {
        $server = $this->getServer();
        $scriptId = time().'_'.uniqid();
        $filename = "provision-{$scriptId}.sh";

        $this->script = ProvisionScript::create([
            'server_id' => $server->id,
            'filename' => $filename,
            'resource_type' => $this->getResourceType(),
            'resource_id' => $this->getResourceId(),
            'run_as_user' => $this->getRunAsUser(),
            'content' => $this->generateScript(),
            'status' => ProvisionScriptStatus::Executing,
        ]);
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
        Log::error("Provision job permanently failed for {$this->getResourceType()}: ".$exception->getMessage());

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
