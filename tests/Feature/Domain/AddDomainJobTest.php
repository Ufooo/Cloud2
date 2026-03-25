<?php

use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Jobs\AddDomainJob;
use Nip\Domain\Models\Certificate;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

it('links new domain to matching wildcard certificate', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create wildcard certificate
    $wildcardCert = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['*.example.com', 'example.com'],
        'active' => true,
    ]);

    // Create new subdomain
    $subDomain = $site->domainRecords()->create([
        'name' => 'api.example.com',
        'type' => DomainRecordType::Alias,
        'status' => DomainRecordStatus::Creating,
        'certificate_id' => null,
    ]);

    // Simulate successful domain addition
    $job = new AddDomainJob($subDomain);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');

    $mockResult = new ExecutionResult(
        output: 'Domain added successfully',
        exitCode: 0,
        duration: 2.0
    );

    $method->invoke($job, $mockResult);

    // Domain should be linked to wildcard certificate
    expect($subDomain->fresh()->status)->toBe(DomainRecordStatus::Enabled);
    expect($subDomain->fresh()->certificate_id)->toBe($wildcardCert->id);
});

it('links new domain to matching exact certificate', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create certificate for specific subdomain
    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['api.example.com'],
        'active' => true,
    ]);

    // Create the subdomain that matches the certificate
    $subDomain = $site->domainRecords()->create([
        'name' => 'api.example.com',
        'type' => DomainRecordType::Alias,
        'status' => DomainRecordStatus::Creating,
        'certificate_id' => null,
    ]);

    $job = new AddDomainJob($subDomain);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');

    $mockResult = new ExecutionResult(
        output: 'Domain added successfully',
        exitCode: 0,
        duration: 2.0
    );

    $method->invoke($job, $mockResult);

    expect($subDomain->fresh()->certificate_id)->toBe($certificate->id);
});

it('does not link domain when no matching certificate exists', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create certificate for different domain
    Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['other.com'],
        'active' => true,
    ]);

    // Create subdomain that doesn't match any certificate
    $subDomain = $site->domainRecords()->create([
        'name' => 'api.example.com',
        'type' => DomainRecordType::Alias,
        'status' => DomainRecordStatus::Creating,
        'certificate_id' => null,
    ]);

    $job = new AddDomainJob($subDomain);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');

    $mockResult = new ExecutionResult(
        output: 'Domain added successfully',
        exitCode: 0,
        duration: 2.0
    );

    $method->invoke($job, $mockResult);

    expect($subDomain->fresh()->certificate_id)->toBeNull();
});

it('does not overwrite existing certificate_id', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create two certificates
    $existingCert = Certificate::factory()->for($site)->create([
        'type' => CertificateType::Existing,
        'status' => CertificateStatus::Installed,
        'domains' => ['api.example.com'],
        'active' => true,
    ]);

    $wildcardCert = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['*.example.com'],
        'active' => true,
    ]);

    // Create domain with existing certificate
    $subDomain = $site->domainRecords()->create([
        'name' => 'api.example.com',
        'type' => DomainRecordType::Alias,
        'status' => DomainRecordStatus::Creating,
        'certificate_id' => $existingCert->id,
    ]);

    $job = new AddDomainJob($subDomain);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');

    $mockResult = new ExecutionResult(
        output: 'Domain added successfully',
        exitCode: 0,
        duration: 2.0
    );

    $method->invoke($job, $mockResult);

    // Should NOT overwrite with wildcard cert
    expect($subDomain->fresh()->certificate_id)->toBe($existingCert->id);
});

it('only links to active installed certificates', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create inactive wildcard certificate
    Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['*.example.com'],
        'active' => false, // Inactive
    ]);

    // Create pending certificate
    Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Pending, // Not installed
        'domains' => ['api.example.com'],
        'active' => true,
    ]);

    // Create subdomain
    $subDomain = $site->domainRecords()->create([
        'name' => 'api.example.com',
        'type' => DomainRecordType::Alias,
        'status' => DomainRecordStatus::Creating,
        'certificate_id' => null,
    ]);

    $job = new AddDomainJob($subDomain);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');

    $mockResult = new ExecutionResult(
        output: 'Domain added successfully',
        exitCode: 0,
        duration: 2.0
    );

    $method->invoke($job, $mockResult);

    // Should not link to inactive or pending certificates
    expect($subDomain->fresh()->certificate_id)->toBeNull();
});

it('generates SSL nginx config when matching certificate exists', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create wildcard certificate
    $wildcardCert = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['*.example.com', 'example.com'],
        'active' => true,
        'path' => '/etc/nginx/ssl/example.com',
    ]);

    // Create new subdomain
    $subDomain = $site->domainRecords()->create([
        'name' => 'api.example.com',
        'type' => DomainRecordType::Alias,
        'status' => DomainRecordStatus::Creating,
        'certificate_id' => null,
        'www_redirect_type' => WwwRedirectType::FromWww,
    ]);

    $job = new AddDomainJob($subDomain);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('generateScript');

    $script = $method->invoke($job);

    // Should contain SSL configuration
    expect($script)->toContain('listen 443 ssl');
    expect($script)->toContain('ssl_certificate');
    expect($script)->toContain('ssl_certificate_key');
    expect($script)->toContain('SSL: Yes');
    expect($script)->toContain('HTTP to HTTPS redirect');
});

it('generates HTTP-only nginx config when no matching certificate exists', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create new subdomain without matching certificate
    $subDomain = $site->domainRecords()->create([
        'name' => 'api.example.com',
        'type' => DomainRecordType::Alias,
        'status' => DomainRecordStatus::Creating,
        'certificate_id' => null,
        'www_redirect_type' => WwwRedirectType::FromWww,
    ]);

    $job = new AddDomainJob($subDomain);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('generateScript');

    $script = $method->invoke($job);

    // Should contain HTTP-only configuration
    expect($script)->toContain('listen 80;');
    expect($script)->toContain('SSL: No');
    expect($script)->not->toContain('listen 443 ssl');
    expect($script)->not->toContain('HTTP to HTTPS redirect');
});

it('generates subdomain exclusion config when SSL is configured', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create wildcard certificate
    Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['*.example.com', 'example.com'],
        'active' => true,
        'path' => '/etc/nginx/ssl/example.com',
    ]);

    // Create new subdomain
    $subDomain = $site->domainRecords()->create([
        'name' => 'api.example.com',
        'type' => DomainRecordType::Alias,
        'status' => DomainRecordStatus::Creating,
        'certificate_id' => null,
        'www_redirect_type' => WwwRedirectType::FromWww,
    ]);

    $job = new AddDomainJob($subDomain);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('generateScript');

    $script = $method->invoke($job);

    // Should contain subdomain exclusion update script
    expect($script)->toContain('Update subdomain redirect exclusions');
    expect($script)->toContain('redirect-subdomains.conf');
    expect($script)->toContain('if ($host = api.example.com)');
});

it('does not generate subdomain exclusion config without SSL', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create subdomain without matching certificate
    $subDomain = $site->domainRecords()->create([
        'name' => 'api.example.com',
        'type' => DomainRecordType::Alias,
        'status' => DomainRecordStatus::Creating,
        'certificate_id' => null,
        'www_redirect_type' => WwwRedirectType::FromWww,
    ]);

    $job = new AddDomainJob($subDomain);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('generateScript');

    $script = $method->invoke($job);

    // Should NOT contain subdomain exclusion update script
    expect($script)->not->toContain('Update subdomain redirect exclusions');
    expect($script)->not->toContain('redirect-subdomains.conf');
});
