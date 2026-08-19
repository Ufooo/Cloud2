<?php

use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Jobs\AddDomainJob;
use Nip\Domain\Models\Certificate;
use Nip\Server\Models\Server;
use Nip\Site\Enums\SiteType;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

function renderWwwRedirect(Site $site, string $domain, WwwRedirectType $type, ?Certificate $certificate = null, bool $wildcard = false): string
{
    return view('provisioning.scripts.partials.nginx-www-redirect', [
        'site' => $site,
        'domain' => $domain,
        'wwwRedirectType' => $type,
        'allowWildcard' => $wildcard,
        'certificate' => $certificate,
    ])->render();
}

it('redirects the www name over https when the certificate covers it', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', 'www.example.com'],
        'active' => true,
    ]);

    $config = renderWwwRedirect($site, 'example.com', WwwRedirectType::FromWww, $certificate);

    expect($config)
        ->toContain('listen 443 ssl')
        ->toContain('return 301 https://example.com$request_uri;');

    // The https block must carry the certificate, otherwise nginx refuses to start.
    expect($config)->toContain($certificate->getCertPath().'/fullchain.crt');
});

it('keeps the www name reachable for acme renewals', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $config = renderWwwRedirect($site, 'example.com', WwwRedirectType::FromWww);

    // Without this the redirect swallows the challenge and the www name can
    // never be revalidated, which is exactly how houg.hu nearly expired.
    expect($config)
        ->toContain('/.well-known/acme-challenge/')
        ->toContain('server_name www.example.com;');
});

it('omits the https block when no certificate covers the www name', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $apexOnly = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com'],
        'active' => true,
    ]);

    $config = renderWwwRedirect($site, 'example.com', WwwRedirectType::FromWww, $apexOnly);

    // A 443 block pointing at a cert that does not cover www would show a
    // browser security warning, so we must not emit one.
    expect($config)
        ->not->toContain('listen 443')
        ->toContain('server_name www.example.com;');
});

it('redirects the apex over https for to_www domains', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', 'www.example.com'],
        'active' => true,
    ]);

    $config = renderWwwRedirect($site, 'example.com', WwwRedirectType::ToWww, $certificate);

    expect($config)
        ->toContain('listen 443 ssl')
        ->toContain('server_name example.com;')
        ->toContain('return 301 https://www.example.com$request_uri;');
});

it('never emits a www redirect for a wildcard domain', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    // A wildcard serves every subdomain, www included. Wildcard and www
    // redirect are mutually exclusive: if it is wildcard, it is wildcard.
    $config = renderWwwRedirect($site, 'example.com', WwwRedirectType::FromWww, null, true);

    expect(trim($config))->toBe('');
});

it('never redirects every subdomain of a wildcard domain to the apex', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $config = view('provisioning.scripts.partials.nginx-server-block', [
        'site' => $site,
        'domain' => 'example.com',
        'wwwRedirectType' => WwwRedirectType::FromWww,
        'allowWildcard' => true,
        'certificate' => null,
        'documentRoot' => '/home/deploy/example.com/public',
        'phpSocket' => '/var/run/php/php8.4-fpm-deploy.sock',
        'siteType' => SiteType::Laravel,
    ])->render();

    // `if ($host != example.com)` would 301 ujbuda.example.com to the apex.
    // thorgym.hu and thorlife.hu serve thousands of such requests a day.
    expect($config)
        ->toContain('server_name example.com *.example.com;')
        ->not->toContain('if ($host !=');
});

it('wires the certificate through from the add domain job', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create(['domain' => 'example.com']);

    Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', 'www.example.com'],
        'active' => true,
    ]);

    $record = $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => DomainRecordType::Primary,
        'status' => DomainRecordStatus::Creating,
        'www_redirect_type' => WwwRedirectType::FromWww,
    ]);

    $job = new AddDomainJob($record);
    $script = (new ReflectionClass($job))->getMethod('generateScript')->invoke($job);

    expect($script)->toContain('return 301 https://example.com$request_uri;');
});

it('forces no www redirect when a domain is wildcard', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $record = $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => DomainRecordType::Primary,
        'status' => DomainRecordStatus::Creating,
        'www_redirect_type' => WwwRedirectType::FromWww,
        'allow_wildcard' => true,
    ]);

    expect($record->fresh()->www_redirect_type)->toBe(WwwRedirectType::None);
});

it('clears the www redirect when an existing domain becomes wildcard', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $record = $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => DomainRecordType::Primary,
        'status' => DomainRecordStatus::Creating,
        'www_redirect_type' => WwwRedirectType::FromWww,
        'allow_wildcard' => false,
    ]);

    $record->update(['allow_wildcard' => true]);

    expect($record->fresh()->www_redirect_type)->toBe(WwwRedirectType::None);
});

it('keeps serving the www name when no certificate can redirect it', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $apexOnly = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com'],
        'active' => true,
    ]);

    $config = view('provisioning.scripts.partials.nginx-server-block', [
        'site' => $site,
        'domain' => 'example.com',
        'wwwRedirectType' => WwwRedirectType::FromWww,
        'allowWildcard' => false,
        'certificate' => $apexOnly,
        'documentRoot' => '/home/deploy/example.com/public',
        'phpSocket' => '/var/run/php/php8.4-fpm-deploy.sock',
        'siteType' => SiteType::Laravel,
    ])->render();

    // Narrowing to the apex while nothing can serve www on 443 leaves the name
    // homeless: it falls through to the catch-all or a neighbouring wildcard
    // site. Never drop a name until something else has taken it over.
    expect($config)->toContain('server_name example.com www.example.com;');
});

it('narrows to the apex once the certificate covers the www name', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $withWww = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', 'www.example.com'],
        'active' => true,
    ]);

    $config = view('provisioning.scripts.partials.nginx-server-block', [
        'site' => $site,
        'domain' => 'example.com',
        'wwwRedirectType' => WwwRedirectType::FromWww,
        'allowWildcard' => false,
        'certificate' => $withWww,
        'documentRoot' => '/home/deploy/example.com/public',
        'phpSocket' => '/var/run/php/php8.4-fpm-deploy.sock',
        'siteType' => SiteType::Laravel,
    ])->render();

    expect($config)->toContain('server_name example.com;');
});
