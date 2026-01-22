<?php

namespace Nip\Composer\Jobs;

use Nip\Composer\Enums\ComposerCredentialStatus;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Events\SiteResourceStatusUpdated;
use Nip\Site\Models\Site;

class SyncComposerAuthJob extends BaseProvisionJob
{
    public function __construct(
        public Site $site,
        public ?int $credentialIdToDelete = null,
    ) {
        $this->updateCredentialsStatus(ComposerCredentialStatus::Syncing);
    }

    protected function getResourceType(): string
    {
        return 'composer_auth';
    }

    protected function getResourceId(): ?int
    {
        return $this->site->id;
    }

    protected function getServer(): Server
    {
        return $this->site->server;
    }

    protected function getRunAsUser(): ?string
    {
        return $this->site->user;
    }

    protected function generateScript(): string
    {
        $authJson = $this->buildAuthJson();

        return view('provisioning.scripts.site.steps.sync-composer-auth', [
            'site' => $this->site,
            'siteRoot' => $this->site->getSiteRoot(),
            'applicationPath' => $this->site->getApplicationPath(),
            'user' => $this->site->user,
            'authJson' => json_encode($authJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        if ($this->credentialIdToDelete) {
            $this->site->composerCredentials()
                ->where('id', $this->credentialIdToDelete)
                ->delete();
        }

        $this->updateCredentialsStatus(ComposerCredentialStatus::Synced);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->updateCredentialsStatus(ComposerCredentialStatus::Failed);
    }

    private function updateCredentialsStatus(ComposerCredentialStatus $status): void
    {
        $this->site->composerCredentials()
            ->where('status', '!=', ComposerCredentialStatus::Deleting)
            ->update(['status' => $status]);

        SiteResourceStatusUpdated::dispatch(
            $this->site,
            'composer_credentials',
            null,
            $status->value,
        );
    }

    /**
     * Build the auth.json content from credentials.
     *
     * @return array<string, array<string, array{username: string, password: string}>>
     */
    private function buildAuthJson(): array
    {
        $httpBasic = [];

        $credentials = $this->site->composerCredentials()
            ->where('status', '!=', ComposerCredentialStatus::Deleting)
            ->get();

        foreach ($credentials as $credential) {
            $httpBasic[$credential->repository] = [
                'username' => $credential->username,
                'password' => $credential->password,
            ];
        }

        if (empty($httpBasic)) {
            return [];
        }

        return ['http-basic' => $httpBasic];
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'provision',
            'composer_auth',
            'site:'.$this->site->id,
            'server:'.$this->site->server_id,
        ];
    }
}
