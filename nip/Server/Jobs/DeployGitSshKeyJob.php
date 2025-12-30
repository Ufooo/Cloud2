<?php

namespace Nip\Server\Jobs;

use Nip\Server\Actions\GenerateGitSshKey;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;

class DeployGitSshKeyJob extends BaseProvisionJob
{
    private string $privateKey;

    public function __construct(
        public Server $server,
    ) {
        // Generate new key pair - public key is saved to DB, private key is stored for deployment
        $keys = app(GenerateGitSshKey::class)->handle($this->server);
        $this->privateKey = $keys['private_key'];
    }

    protected function getResourceType(): string
    {
        return 'server';
    }

    protected function getResourceId(): ?int
    {
        return $this->server->id;
    }

    protected function getServer(): Server
    {
        return $this->server;
    }

    protected function generateScript(): string
    {
        // Get all unique users from sites on this server
        $users = $this->server->sites()
            ->pluck('user')
            ->unique()
            ->values()
            ->toArray();

        // If no sites yet, use a default user
        if (empty($users)) {
            $users = ['forge'];
        }

        return view('provisioning.partials.git-ssh-key', [
            'gitPrivateKey' => $this->privateKey,
            'users' => $users,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        // Key is already saved to DB in constructor via GenerateGitSshKey action
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Clear the public key from DB since deployment failed
        $this->server->update(['git_public_key' => null]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'provision',
            'server',
            'server:'.$this->server->id,
            'git-ssh-key',
        ];
    }
}
