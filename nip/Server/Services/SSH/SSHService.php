<?php

namespace Nip\Server\Services\SSH;

use Exception;
use Illuminate\Support\Facades\Log;
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
                Log::info("SSH connection attempt {$attempt} to {$this->server->ip_address} as {$this->connectedUser}");

                $this->connection = new SSH2(
                    $this->server->ip_address,
                    $this->server->ssh_port ?? 22
                );

                $this->connection->setTimeout($this->timeout);

                $privateKey = $this->getPrivateKey();

                if ($this->connection->login($this->connectedUser, $privateKey)) {
                    Log::info("SSH connection successful to {$this->server->ip_address} as {$this->connectedUser}");

                    return;
                }

                throw new Exception("SSH authentication failed for user {$this->connectedUser}");
            } catch (Exception $e) {
                $lastException = $e;
                Log::warning("SSH connection attempt {$attempt} failed: ".$e->getMessage());

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

        Log::debug("Using server's own SSH key for authentication");

        return PublicKeyLoader::loadPrivateKey($privateKeyString);
    }

    public function exec(string $command): ExecutionResult
    {
        if (! $this->connection) {
            throw new Exception('Not connected to server');
        }

        Log::debug("Executing command on {$this->server->ip_address}: {$command}");

        $startTime = microtime(true);
        $output = $this->connection->exec($command);
        $exitCode = $this->connection->getExitStatus();
        $duration = microtime(true) - $startTime;

        Log::debug("Command completed with exit code {$exitCode} in {$duration}s");

        return new ExecutionResult(
            output: $output,
            exitCode: $exitCode,
            duration: $duration
        );
    }

    public function executeScript(string $scriptContent): ExecutionResult
    {
        $scriptId = time().'_'.uniqid();

        $homeDir = $this->connectedUser === 'root' ? '/root' : "/home/{$this->connectedUser}";
        $scriptDir = "{$homeDir}/.netipar";
        $remotePath = "{$scriptDir}/provision-{$scriptId}.sh";
        $outputPath = "{$scriptDir}/provision-{$scriptId}.output";

        try {
            $this->exec("mkdir -p {$scriptDir}");

            Log::info("Uploading script to {$remotePath}");
            $this->uploadContent($scriptContent, $remotePath);

            $this->exec("chmod +x {$remotePath}");

            // Execute script and tee output to .output file for debugging (like Forge)
            Log::info("Executing script {$remotePath} as {$this->connectedUser}");
            $result = $this->exec("bash {$remotePath} 2>&1 | tee {$outputPath}; exit \${PIPESTATUS[0]}");

            Log::info("Script output saved to {$outputPath}");

            return $result;

        } catch (Exception $e) {
            Log::error("Script execution failed. Script kept at {$remotePath}, output at {$outputPath}");

            throw $e;
        }
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
        } catch (Exception $e) {
            Log::warning("Failed to read file {$path}: ".$e->getMessage());

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
