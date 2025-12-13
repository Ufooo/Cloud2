<?php

namespace Nip\Site\Jobs;

use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Models\Site;

class InstallSiteJob extends BaseProvisionJob
{
    public function __construct(
        public Site $site,
    ) {}

    protected function getResourceType(): string
    {
        return 'site';
    }

    protected function getResourceId(): ?int
    {
        return $this->site->id;
    }

    protected function getServer(): Server
    {
        return $this->site->server;
    }

    protected function generateScript(): string
    {
        $phpVersion = $this->site->php_version ?? $this->site->server->php_version;
        $fullPath = $this->site->getFullPath();
        $webDirectory = $this->site->web_directory;
        $domain = $this->site->domain;
        $user = $this->site->user;

        $nginxConfig = view('provisioning.scripts.site.partials.nginx-config', [
            'site' => $this->site,
            'user' => $user,
            'domain' => $domain,
            'fullPath' => $fullPath,
            'webDirectory' => $webDirectory,
            'phpVersion' => $phpVersion,
            'siteType' => $this->site->type,
            'allowWildcard' => $this->site->allow_wildcard,
            'wwwRedirectType' => $this->site->www_redirect_type,
            'isIsolated' => $this->site->is_isolated,
        ])->render();

        $defaultIndexScript = $this->generateDefaultIndexScript($webDirectory);
        $isolatedFpmScript = $this->generateIsolatedFpmScript($phpVersion, $domain, $user, $fullPath);

        return view('provisioning.scripts.site.install', [
            'site' => $this->site,
            'user' => $user,
            'domain' => $domain,
            'fullPath' => $fullPath,
            'webPath' => $this->site->getWebPath(),
            'webDirectory' => $webDirectory,
            'phpVersion' => $phpVersion,
            'siteType' => $this->site->type,
            'allowWildcard' => $this->site->allow_wildcard,
            'wwwRedirectType' => $this->site->www_redirect_type,
            'isIsolated' => $this->site->is_isolated,
            'nginxConfig' => $nginxConfig,
            'defaultIndexScript' => $defaultIndexScript,
            'isolatedFpmScript' => $isolatedFpmScript,
        ])->render();
    }

    protected function generateDefaultIndexScript(string $webDirectory): string
    {
        return view('provisioning.scripts.site.partials.default-index-html', [
            'webDirectory' => $webDirectory,
            'domain' => $this->site->domain,
        ])->render();
    }

    protected function generateIsolatedFpmScript(string $phpVersion, string $domain, string $user, string $fullPath): string
    {
        if (! $this->site->is_isolated) {
            return '';
        }

        return view('provisioning.scripts.site.partials.isolated-fpm-pool', [
            'phpVersion' => $phpVersion,
            'domain' => $domain,
            'user' => $user,
            'fullPath' => $fullPath,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->site->update([
            'status' => SiteStatus::Installed,
        ]);
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->site->update([
            'status' => SiteStatus::Failed,
        ]);
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'provision',
            'site',
            'site:'.$this->site->id,
            'server:'.$this->site->server_id,
            'install',
        ];
    }
}
