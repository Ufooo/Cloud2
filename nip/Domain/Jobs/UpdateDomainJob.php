<?php

namespace Nip\Domain\Jobs;

use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Models\DomainRecord;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\WwwRedirectType;

class UpdateDomainJob extends BaseProvisionJob
{
    public function __construct(
        public DomainRecord $domainRecord,
    ) {}

    protected function getResourceType(): string
    {
        return 'domain';
    }

    protected function getResourceId(): ?int
    {
        return $this->domainRecord->id;
    }

    protected function getServer(): Server
    {
        return $this->domainRecord->site->server;
    }

    protected function generateScript(): string
    {
        $site = $this->domainRecord->site;
        $isRedirect = $this->domainRecord->type === DomainRecordType::Redirect;

        if ($isRedirect) {
            $matchingCertificate = $this->findMatchingCertificate();

            $nginxConfig = view('provisioning.scripts.domain.partials.nginx-config-redirect', [
                'site' => $site,
                'domain' => $this->domainRecord->name,
                'redirectTarget' => $this->domainRecord->redirect_target,
                'allowWildcard' => $this->domainRecord->allow_wildcard,
                'certificate' => $matchingCertificate,
            ])->render();

            return view('provisioning.scripts.domain.update', [
                'site' => $site,
                'domainRecord' => $this->domainRecord,
                'domain' => $this->domainRecord->name,
                'nginxConfig' => $nginxConfig,
                'wwwRedirectConfig' => '',
            ])->render();
        }

        $primaryDomain = $site->domainRecords()
            ->where('type', DomainRecordType::Primary->value)
            ->value('name');

        $nginxConfig = view('provisioning.scripts.domain.partials.nginx-config', [
            'site' => $site,
            'domain' => $this->domainRecord->name,
            'applicationPath' => $site->getApplicationPath(),
            'documentRoot' => $site->getDocumentRoot(),
            'phpSocket' => $site->getPhpSocketPath(),
            'siteType' => $site->type,
            'allowWildcard' => $this->domainRecord->allow_wildcard,
            'wwwRedirectType' => $this->domainRecord->www_redirect_type,
            'primaryDomain' => $primaryDomain,
        ])->render();

        $wwwRedirectConfig = '';
        if ($this->domainRecord->www_redirect_type !== WwwRedirectType::ToPrimary) {
            $wwwRedirectConfig = view('provisioning.scripts.partials.nginx-www-redirect', [
                'domain' => $this->domainRecord->name,
                'wwwRedirectType' => $this->domainRecord->www_redirect_type,
                'allowWildcard' => $this->domainRecord->allow_wildcard,
            ])->render();
        }

        return view('provisioning.scripts.domain.update', [
            'site' => $site,
            'domainRecord' => $this->domainRecord,
            'domain' => $this->domainRecord->name,
            'nginxConfig' => $nginxConfig,
            'wwwRedirectConfig' => $wwwRedirectConfig,
        ])->render();
    }

    protected function findMatchingCertificate(): ?\Nip\Domain\Models\Certificate
    {
        return $this->domainRecord->site->certificates()
            ->where('active', true)
            ->where('status', \Nip\Domain\Enums\CertificateStatus::Installed)
            ->get()
            ->first(fn ($cert) => $cert->coversDomain($this->domainRecord->name));
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        // Domain record already updated in controller
    }

    protected function handleFailure(\Throwable $exception): void
    {
        // Config update failed - the old config remains active
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'provision',
            'domain',
            'domain:'.$this->domainRecord->id,
            'site:'.$this->domainRecord->site_id,
            'server:'.$this->domainRecord->site->server_id,
            'update',
        ];
    }
}
