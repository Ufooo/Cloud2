<?php

namespace Nip\Server\Services;

use Exception;
use Nip\Server\Models\Server;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class SSHService
{
    protected SSH2 $ssh;

    protected int $maxRetries = 3;

    protected int $retryDelay = 2;

    public function __construct(
        protected Server $server,
        protected string $username = 'root',
    ) {}

    /**
     * Connect to the server via SSH.
     *
     * @throws Exception
     */
    public function connect(): self
    {
        if (! $this->server->ssh_private_key) {
            throw new Exception("Server {$this->server->name} does not have an SSH private key.");
        }

        if (! $this->server->ip_address) {
            throw new Exception("Server {$this->server->name} does not have an IP address.");
        }

        $this->ssh = new SSH2($this->server->ip_address, (int) $this->server->ssh_port);
        $privateKey = PublicKeyLoader::load($this->server->ssh_private_key);

        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            if ($this->ssh->login($this->username, $privateKey)) {
                return $this;
            }

            if ($attempt < $this->maxRetries) {
                sleep($this->retryDelay);
            }
        }

        throw new Exception("Failed to authenticate to server {$this->server->name} after {$this->maxRetries} attempts.");
    }

    /**
     * Execute a command on the server.
     *
     * @throws Exception
     */
    public function run(string $command, int $timeout = 60): string
    {
        if (! isset($this->ssh)) {
            $this->connect();
        }

        $this->ssh->setTimeout($timeout);
        $output = $this->ssh->exec($command);

        if ($output === false) {
            throw new Exception("Failed to execute command on server {$this->server->name}.");
        }

        return $output;
    }

    /**
     * Execute a command as root (with sudo).
     */
    public function runAsRoot(string $command, int $timeout = 60): string
    {
        if ($this->username === 'root') {
            return $this->run($command, $timeout);
        }

        return $this->run("sudo {$command}", $timeout);
    }

    /**
     * Upload a string content to a file on the server.
     */
    public function upload(string $remotePath, string $content): bool
    {
        if (! isset($this->ssh)) {
            $this->connect();
        }

        $escapedContent = escapeshellarg($content);
        $escapedPath = escapeshellarg($remotePath);

        $this->run("echo {$escapedContent} > {$escapedPath}");

        return true;
    }

    /**
     * Read a file from the server.
     */
    public function read(string $remotePath): string
    {
        $escapedPath = escapeshellarg($remotePath);

        return $this->run("cat {$escapedPath}");
    }

    /**
     * Check if a file or directory exists on the server.
     */
    public function exists(string $path): bool
    {
        $escapedPath = escapeshellarg($path);
        $result = $this->run("test -e {$escapedPath} && echo 'yes' || echo 'no'");

        return trim($result) === 'yes';
    }

    /**
     * Disconnect from the server.
     */
    public function disconnect(): void
    {
        if (isset($this->ssh)) {
            $this->ssh->disconnect();
        }
    }

    /**
     * Get the exit status of the last command.
     */
    public function getExitStatus(): int|false
    {
        return $this->ssh->getExitStatus();
    }

    /**
     * Set the username for SSH connection.
     */
    public function asUser(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Set the maximum number of retries for connection.
     */
    public function withRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Static factory method for creating an SSHService instance.
     */
    public static function for(Server $server, string $username = 'root'): self
    {
        return new self($server, $username);
    }
}
