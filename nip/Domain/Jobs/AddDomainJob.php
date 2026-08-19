<?php

namespace Nip\Domain\Jobs;

use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Models\Certificate;
use Nip\Domain\Models\DomainRecord;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\WwwRedirectType;

class AddDomainJob extends BaseProvisionJob
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
        $matchingCertificate = $this->findMatchingCertificate();
        $isRedirect = $this->domainRecord->type === DomainRecordType::Redirect;

        $nginxConfig = $this->generateNginxConfig($site, $matchingCertificate);
        // Redirect-type domains handle their own www/SSL logic in the server block,
        // so we skip the standalone redirect snippets.
        $wwwRedirectConfig = $isRedirect ? '' : $this->generateWwwRedirectConfig($matchingCertificate);
        $sslRedirectConfig = (! $isRedirect && $matchingCertificate) ? $this->generateSslRedirectConfig() : '';
        $subdomainExclusionConfig = (! $isRedirect && $matchingCertificate) ? $this->generateSubdomainExclusionConfig() : '';

        return view('provisioning.scripts.domain.add', [
            'site' => $site,
            'domainRecord' => $this->domainRecord,
            'domain' => $this->domainRecord->name,
            'nginxConfig' => $nginxConfig,
            'wwwRedirectConfig' => $wwwRedirectConfig,
            'sslRedirectConfig' => $sslRedirectConfig,
            'subdomainExclusionConfig' => $subdomainExclusionConfig,
            'certificate' => $matchingCertificate,
        ])->render();
    }

    protected function generateNginxConfig(\Nip\Site\Models\Site $site, ?Certificate $certificate = null): string
    {
        if ($this->domainRecord->type === DomainRecordType::Redirect) {
            return view('provisioning.scripts.domain.partials.nginx-config-redirect', [
                'site' => $site,
                'domain' => $this->domainRecord->name,
                'redirectTarget' => $this->domainRecord->redirect_target,
                'allowWildcard' => $this->domainRecord->allow_wildcard,
                'certificate' => $certificate,
            ])->render();
        }

        $primaryDomain = $site->domainRecords()
            ->where('type', DomainRecordType::Primary->value)
            ->value('name');

        return view('provisioning.scripts.domain.partials.nginx-config', [
            'site' => $site,
            'domain' => $this->domainRecord->name,
            'applicationPath' => $site->getApplicationPath(),
            'documentRoot' => $site->getDocumentRoot(),
            'phpSocket' => $site->getPhpSocketPath(),
            'siteType' => $site->type,
            'allowWildcard' => $this->domainRecord->allow_wildcard,
            'wildcardBehavior' => $this->domainRecord->wildcard_behavior,
            'wwwRedirectType' => $this->domainRecord->www_redirect_type,
            'primaryDomain' => $primaryDomain,
            'certificate' => $certificate,
        ])->render();
    }

    /**
     * Find an active, installed certificate that covers this domain.
     */
    protected function findMatchingCertificate(): ?Certificate
    {
        return $this->domainRecord->site->certificates()
            ->where('active', true)
            ->where('status', CertificateStatus::Installed)
            ->get()
            ->first(fn ($cert) => $cert->coversDomain($this->domainRecord->name));
    }

    /**
     * Generate HTTP to HTTPS redirect config for SSL-enabled domains.
     */
    protected function generateSslRedirectConfig(): string
    {
        return view('provisioning.scripts.domain.partials.ssl-redirect', [
            'domain' => $this->domainRecord->name,
            'site' => $this->domainRecord->site,
        ])->render();
    }

    /**
     * Generate script to update subdomain redirect exclusions.
     * This ensures new domains with SSL are excluded from any wildcard subdomain redirects.
     */
    protected function generateSubdomainExclusionConfig(): string
    {
        return view('provisioning.scripts.domain.partials.update-subdomain-exclusion', [
            'domain' => $this->domainRecord->name,
            'site' => $this->domainRecord->site,
        ])->render();
    }

    protected function generateWwwRedirectConfig(?Certificate $certificate = null): string
    {
        if ($this->domainRecord->www_redirect_type === WwwRedirectType::ToPrimary) {
            return '';
        }

        return view('provisioning.scripts.partials.nginx-www-redirect', [
            'site' => $this->domainRecord->site,
            'domain' => $this->domainRecord->name,
            'wwwRedirectType' => $this->domainRecord->www_redirect_type,
            'allowWildcard' => $this->domainRecord->allow_wildcard,
            'certificate' => $certificate,
        ])->render();
    }

    protected function handleSuccess(ExecutionResult $result): void
    {
        $this->domainRecord->update([
            'status' => DomainRecordStatus::Enabled,
        ]);

        $this->linkToMatchingCertificate();
    }

    /**
     * Link this domain record to an existing certificate that covers it (wildcard or exact match).
     */
    protected function linkToMatchingCertificate(): void
    {
        // Skip if already has a certificate
        if ($this->domainRecord->certificate_id) {
            return;
        }

        $matchingCertificate = $this->findMatchingCertificate();

        if ($matchingCertificate) {
            $this->domainRecord->update(['certificate_id' => $matchingCertificate->id]);
        }
    }

    protected function handleFailure(\Throwable $exception): void
    {
        $this->domainRecord->update([
            'status' => DomainRecordStatus::Failed,
        ]);
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
            'add',
        ];
    }
}
