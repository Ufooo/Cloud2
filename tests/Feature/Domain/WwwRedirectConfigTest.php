<?php

use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Jobs\AddDomainJob;
use Nip\Domain\Models\Certificate;
use Nip\Server\Models\Server;
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

it('emits nothing for wildcard domains', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $config = renderWwwRedirect($site, 'example.com', WwwRedirectType::FromWww, null, true);

    expect(trim($config))->toBe('');
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
