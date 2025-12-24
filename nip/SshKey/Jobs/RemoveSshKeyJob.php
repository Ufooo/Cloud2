<?php

namespace Nip\SshKey\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\SSHService;

class RemoveSshKeyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public Server $server,
        public string $username,
        public string $publicKey,
        public string $keyName
    ) {
        $this->onQueue('provisioning');
    }

    public function handle(SSHService $ssh): void
    {
        try {
            Log::info("Removing SSH key '{$this->keyName}' for user {$this->username} on server {$this->server->id}");

            $ssh->connect($this->server);

            $script = $this->generateScript();
            $result = $ssh->exec($script);

            if ($result->isSuccessful()) {
                Log::info("SSH key '{$this->keyName}' successfully removed for user {$this->username}");
            } else {
                Log::warning("Failed to remove SSH key: {$result->output}");
            }

        } catch (\Exception $e) {
            Log::error('Failed to remove SSH key: '.$e->getMessage());

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 120);
            }
        } finally {
            $ssh->disconnect();
        }
    }

    protected function generateScript(): string
    {
        $homeDir = $this->username === 'root' ? '/root' : "/home/{$this->username}";
        $escapedKey = addslashes($this->publicKey);

        return <<<BASH
AUTH_KEYS="{$homeDir}/.ssh/authorized_keys"

if [ -f "\${AUTH_KEYS}" ]; then
    # Create temp file without the key and its comment
    grep -v "# Netipar: {$this->keyName}" "\${AUTH_KEYS}" | grep -v "{$escapedKey}" > "\${AUTH_KEYS}.tmp" || true
    mv "\${AUTH_KEYS}.tmp" "\${AUTH_KEYS}"
    chown {$this->username}:{$this->username} "\${AUTH_KEYS}"
    chmod 600 "\${AUTH_KEYS}"
    echo "SSH key removed successfully"
else
    echo "authorized_keys file not found"
fi
BASH;
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'ssh_key_remove',
            'server:'.$this->server->id,
            'user:'.$this->username,
        ];
    }
}
