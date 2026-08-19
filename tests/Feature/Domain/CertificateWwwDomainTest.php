<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

beforeEach(function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());
});

function storeHttpCertificate(Site $site, string $domain): void
{
    test()->post(route('sites.certificates.store', $site), [
        'type' => CertificateType::LetsEncrypt->value,
        'domain' => $domain,
        'verification_method' => 'http',
        'key_algorithm' => 'ecdsa',
    ]);
}

it('includes the www domain in http-01 certificates when the record redirects from www', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => DomainRecordType::Primary,
        'www_redirect_type' => WwwRedirectType::FromWww,
    ]);

    storeHttpCertificate($site, 'example.com');

    expect($site->certificates()->sole()->domains)
        ->toContain('example.com')
        ->toContain('www.example.com');
});

it('includes the www domain in http-01 certificates when the record redirects to www', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => DomainRecordType::Primary,
        'www_redirect_type' => WwwRedirectType::ToWww,
    ]);

    storeHttpCertificate($site, 'example.com');

    expect($site->certificates()->sole()->domains)->toContain('www.example.com');
});

it('leaves the www domain out when the record has no www redirect', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $site->domainRecords()->create([
        'name' => 'backend.example.com',
        'type' => DomainRecordType::Alias,
        'www_redirect_type' => WwwRedirectType::None,
    ]);

    storeHttpCertificate($site, 'backend.example.com');

    expect($site->certificates()->sole()->domains)
        ->toContain('backend.example.com')
        ->not->toContain('www.backend.example.com');
});

it('leaves the www domain out for wildcard records', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => DomainRecordType::Primary,
        'www_redirect_type' => WwwRedirectType::FromWww,
        'allow_wildcard' => true,
    ]);

    storeHttpCertificate($site, 'example.com');

    expect($site->certificates()->sole()->domains)->not->toContain('www.example.com');
});
