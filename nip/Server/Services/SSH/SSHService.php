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
        $this->connectedUser = $asUser ?? 'root';
        $this->establishConnection();

        return $this;
    }

    public function getConnectedUser(): string
    {
        return $this->connectedUser;
    }

    private function connectionUser(): string
    {
        return $this->server->ssh_user ?? $this->connectedUser;
    }

    private function hasJumpHost(): bool
    {
        return (bool) $this->server->jump_address;
    }

    private function usesSudo(): bool
    {
        return (bool) $this->server->ssh_user;
    }

    private function establishConnection(): void
    {
        $lastException = null;
        $connectionUser = $this->connectionUser();

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $this->connection = new SSH2(
                    $this->server->ip_address,
                    $this->server->ssh_port ?? 22
                );

                $this->connection->setTimeout($this->timeout);

                $privateKey = $this->getPrivateKey();

                if ($this->connection->login($connectionUser, $privateKey)) {
                    return;
                }

                throw new Exception("SSH authentication failed for user {$connectionUser}");
            } catch (Exception $e) {
                $lastException = $e;

                if ($attempt < $this->maxRetries) {
                    sleep(pow(2, $attempt - 1));
                }
            }
        }

        throw new SSHConnectionException(
            "Failed to connect to server {$this->server->ip_address} as {$connectionUser} after {$this->maxRetries} attempts",
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

    private function buildCommand(string $command): string
    {
        if (! $this->hasJumpHost()) {
            return $command;
        }

        $port = $this->server->jump_port ?? 22;
        $user = $this->server->jump_user;
        $address = $this->server->jump_address;

        return "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 -p {$port} {$user}@{$address} ".escapeshellarg($command);
    }

    public function exec(string $command): ExecutionResult
    {
        if (! $this->connection) {
            throw new Exception('Not connected to server');
        }

        $startTime = microtime(true);
        $output = $this->connection->exec($this->buildCommand($command));
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

        $scriptUser = $this->hasJumpHost()
            ? $this->server->jump_user
            : $this->connectedUser;

        $homeDir = $scriptUser === 'root' ? '/root' : "/home/{$scriptUser}";
        $scriptDir = "{$homeDir}/.netipar";
        $remotePath = "{$scriptDir}/provision-{$scriptId}.sh";
        $outputPath = "{$scriptDir}/provision-{$scriptId}.output";

        $this->exec("mkdir -p {$scriptDir}");
        $this->uploadContent($scriptContent, $remotePath);
        $this->exec("chmod +x {$remotePath}");

        $bashCmd = ($this->usesSudo() && ! $this->hasJumpHost()) ? 'sudo bash' : 'bash';

        if ($onOutput) {
            return $this->execWithStreaming(
                "{$bashCmd} {$remotePath} 2>&1 | tee {$outputPath}; exit \${PIPESTATUS[0]}",
                $onOutput
            );
        }

        return $this->exec("{$bashCmd} {$remotePath} 2>&1 | tee {$outputPath}; exit \${PIPESTATUS[0]}");
    }

    public function execWithStreaming(string $command, callable $onOutput): ExecutionResult
    {
        if (! $this->connection) {
            throw new Exception('Not connected to server');
        }

        $startTime = microtime(true);
        $fullOutput = '';

        $this->connection->exec($this->buildCommand($command), function ($output) use (&$fullOutput, $onOutput) {
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
