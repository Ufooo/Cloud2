<?php

namespace Nip\Composer\Jobs;

use Nip\Composer\Enums\ComposerCredentialStatus;
use Nip\Composer\Models\ComposerCredential;
use Nip\Server\Events\ServerResourceStatusUpdated;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\UnixUser\Models\UnixUser;

class SyncServerComposerAuthJob extends BaseProvisionJob
{
    public function __construct(
        public UnixUser $unixUser,
        public ?int $credentialIdToDelete = null,
    ) {
        $this->updateCredentialsStatus(ComposerCredentialStatus::Syncing);
    }

    protected function getResourceType(): string
    {
        return 'server_composer_auth';
    }

    protected function getResourceId(): ?int
    {
        return $this->unixUser->server_id;
    }

    protected function getServer(): Server
    {
        return $this->unixUser->server;
    }

    protected function generateScript(): string
    {
        $credentials = $this->getUserLevelCredentials()
            ->where('status', '!=', ComposerCredentialStatus::Deleting)
            ->get();

        $jqCommands = [];
        foreach ($credentials as $credential) {
            $jqCommands[] = $this->buildJqCommand($credential->repository, $credential->username, $credential->password);
        }

        $deletingCredentials = $this->getUserLevelCredentials()
            ->where('status', ComposerCredentialStatus::Deleting)
            ->get();

        foreach ($deletingCredentials as $credential) {
            $jqCommands[] = $this->buildJqDeleteCommand($credential->repository);
        }

        return view('provisioning.scripts.server.steps.sync-server-composer-auth', [
            'server' => $this->unixUser->server,
            'user' => $this->unixUser->username,
            'jqCommands' => $jqCommands,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        if ($this->credentialIdToDelete) {
            ComposerCredential::where('id', $this->credentialIdToDelete)->delete();
        }

        $this->updateCredentialsStatus(ComposerCredentialStatus::Synced);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->updateCredentialsStatus(ComposerCredentialStatus::Failed);
    }

    private function updateCredentialsStatus(ComposerCredentialStatus $status): void
    {
        $this->getUserLevelCredentials()
            ->where('status', '!=', ComposerCredentialStatus::Deleting)
            ->update(['status' => $status]);

        ServerResourceStatusUpdated::dispatch(
            $this->unixUser->server,
            'server_composer_credentials',
            null,
            $status->value,
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ComposerCredential>
     */
    private function getUserLevelCredentials(): \Illuminate\Database\Eloquent\Builder
    {
        return ComposerCredential::query()
            ->where('unix_user_id', $this->unixUser->id)
            ->whereNull('site_id');
    }

    private function buildJqCommand(string $repository, string $username, string $password): string
    {
        $escapedRepo = addslashes($repository);
        $escapedUser = addslashes($username);
        $escapedPass = addslashes($password);

        return ".\"http-basic\".\"$escapedRepo\" = { \"username\": \"$escapedUser\", \"password\": \"$escapedPass\" }";
    }

    private function buildJqDeleteCommand(string $repository): string
    {
        $escapedRepo = addslashes($repository);

        return "del(.\"http-basic\".\"$escapedRepo\")";
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'provision',
            'server_composer_auth',
            'server:'.$this->unixUser->server_id,
            'unix_user:'.$this->unixUser->id,
        ];
    }
}
