<?php

namespace Nip\Server\Services\SSH;

use Exception;
use Nip\Server\Models\Server;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class SSHService
{
    private ?SSH2 $connection = null;

    private Server $server;

    private string $connectedUser;

    private int $maxRetries = 3;

    private int $timeout = 30;

    public function connect(Server $server, ?string $asUser = null): self
    {
        $this->server = $server;
        $this->connectedUser = $asUser ?? $server->ssh_user ?? 'root';
        $this->establishConnection();

        return $this;
    }

    public function getConnectedUser(): string
    {
        return $this->connectedUser;
    }

    private function establishConnection(): void
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $this->connection = new SSH2(
                    $this->server->ip_address,
                    $this->server->ssh_port ?? 22
                );

                $this->connection->setTimeout($this->timeout);

                $privateKey = $this->getPrivateKey();

                if ($this->connection->login($this->connectedUser, $privateKey)) {
                    return;
                }

                throw new Exception("SSH authentication failed for user {$this->connectedUser}");
            } catch (Exception $e) {
                $lastException = $e;

                if ($attempt < $this->maxRetries) {
                    sleep(pow(2, $attempt - 1));
                }
            }
        }

        throw new SSHConnectionException(
            "Failed to connect to server {$this->server->ip_address} as {$this->connectedUser} after {$this->maxRetries} attempts",
            previous: $lastException
        );
    }

    private function getPrivateKey()
    {
        $privateKeyString = $this->server->getSshPrivateKey();

        if (! $privateKeyString) {
            throw new SSHConnectionException("No SSH key configured for server {$this->server->name}");
        }

        return PublicKeyLoader::loadPrivateKey($privateKeyString);
    }

    public function exec(string $command): ExecutionResult
    {
        if (! $this->connection) {
            throw new Exception('Not connected to server');
        }

        $startTime = microtime(true);
        $output = $this->connection->exec($command);
        $exitCode = $this->connection->getExitStatus();
        $duration = microtime(true) - $startTime;

        return new ExecutionResult(
            output: $output,
            exitCode: $exitCode,
            duration: $duration
        );
    }

    public function executeScript(string $scriptContent, ?callable $onOutput = null): ExecutionResult
    {
        $scriptId = time().'_'.uniqid();

        $homeDir = $this->connectedUser === 'root' ? '/root' : "/home/{$this->connectedUser}";
        $scriptDir = "{$homeDir}/.netipar";
        $remotePath = "{$scriptDir}/provision-{$scriptId}.sh";
        $outputPath = "{$scriptDir}/provision-{$scriptId}.output";

        $this->exec("mkdir -p {$scriptDir}");
        $this->uploadContent($scriptContent, $remotePath);
        $this->exec("chmod +x {$remotePath}");

        if ($onOutput) {
            return $this->execWithStreaming(
                "bash {$remotePath} 2>&1 | tee {$outputPath}; exit \${PIPESTATUS[0]}",
                $onOutput
            );
        }

        return $this->exec("bash {$remotePath} 2>&1 | tee {$outputPath}; exit \${PIPESTATUS[0]}");
    }

    /**
     * Execute a command with real-time output streaming.
     */
    public function execWithStreaming(string $command, callable $onOutput): ExecutionResult
    {
        if (! $this->connection) {
            throw new Exception('Not connected to server');
        }

        $startTime = microtime(true);
        $fullOutput = '';

        // Use phpseclib's callback mechanism for streaming
        $this->connection->exec($command, function ($output) use (&$fullOutput, $onOutput) {
            $fullOutput .= $output;
            $onOutput($output, $fullOutput);
        });

        $exitCode = $this->connection->getExitStatus();
        $duration = microtime(true) - $startTime;

        return new ExecutionResult(
            output: $fullOutput,
            exitCode: $exitCode,
            duration: $duration
        );
    }

    public function uploadContent(string $content, string $remotePath): void
    {
        if (! $this->connection) {
            throw new Exception('Not connected to server');
        }

        $escapedContent = base64_encode($content);
        $this->exec("echo '{$escapedContent}' | base64 -d > {$remotePath}");
    }

    public function fileExists(string $path): bool
    {
        $result = $this->exec("test -f {$path} && echo 'EXISTS' || echo 'NOT_EXISTS'");

        return str_contains($result->output, 'EXISTS');
    }

    public function isServiceRunning(string $serviceName): bool
    {
        $result = $this->exec("systemctl is-active {$serviceName} 2>/dev/null || echo 'inactive'");

        return trim($result->output) === 'active';
    }

    public function getFileContent(string $path): ?string
    {
        try {
            $result = $this->exec("cat {$path}");

            return $result->isSuccessful() ? $result->output : null;
        } catch (Exception) {
            return null;
        }
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connection->isConnected();
    }

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        if ($this->connection) {
            $this->connection->setTimeout($seconds);
        }

        return $this;
    }

    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = $retries;

        return $this;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
