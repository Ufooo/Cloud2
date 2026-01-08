<?php

use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Jobs\EnableSslJob;
use Nip\Domain\Models\Certificate;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Models\Site;

it('updates domain records certificate_id when ssl is enabled', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create primary domain
    $primaryDomain = $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => DomainRecordType::Primary,
        'certificate_id' => null,
    ]);

    // Create certificate for the primary domain
    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com'],
        'active' => false,
    ]);

    // Simulate successful SSL enablement
    $job = new EnableSslJob($certificate);

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');

    $mockResult = new ExecutionResult(
        output: 'SSL enabled successfully',
        exitCode: 0,
        duration: 3.0
    );

    $method->invoke($job, $mockResult);

    expect($certificate->fresh()->active)->toBeTrue();
    expect($primaryDomain->fresh()->certificate_id)->toBe($certificate->id);
});

it('allows different domains to have different certificates', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create primary domain
    $primaryDomain = $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => DomainRecordType::Primary,
        'certificate_id' => null,
    ]);

    // Create alias domain
    $aliasDomain = $site->domainRecords()->create([
        'name' => 'alias.com',
        'type' => DomainRecordType::Alias,
        'certificate_id' => null,
    ]);

    // Create certificate for primary domain
    $primaryCertificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com'],
        'active' => false,
    ]);

    // Create separate certificate for alias domain
    $aliasCertificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['alias.com'],
        'active' => false,
    ]);

    // Enable SSL for primary domain
    $primaryJob = new EnableSslJob($primaryCertificate);
    $reflection = new ReflectionClass($primaryJob);
    $method = $reflection->getMethod('handleSuccess');
    $mockResult = new ExecutionResult(
        output: 'SSL enabled successfully',
        exitCode: 0,
        duration: 3.0
    );
    $method->invoke($primaryJob, $mockResult);

    // Enable SSL for alias domain
    $aliasJob = new EnableSslJob($aliasCertificate);
    $reflection = new ReflectionClass($aliasJob);
    $method = $reflection->getMethod('handleSuccess');
    $method->invoke($aliasJob, $mockResult);

    // Verify each domain has its own certificate
    expect($primaryDomain->fresh()->certificate_id)->toBe($primaryCertificate->id);
    expect($aliasDomain->fresh()->certificate_id)->toBe($aliasCertificate->id);
    expect($primaryDomain->fresh()->certificate_id)->not->toBe($aliasDomain->fresh()->certificate_id);
});

it('does not overwrite existing certificate_id on domain record', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create existing certificate
    $existingCertificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com'],
        'active' => true,
    ]);

    // Create domain with existing certificate_id
    $domain = $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => DomainRecordType::Primary,
        'certificate_id' => $existingCertificate->id,
    ]);

    // Create new certificate for the same domain
    $newCertificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com'],
        'active' => false,
    ]);

    // Try to enable SSL with new certificate
    $job = new EnableSslJob($newCertificate);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');

    $mockResult = new ExecutionResult(
        output: 'SSL enabled successfully',
        exitCode: 0,
        duration: 3.0
    );

    $method->invoke($job, $mockResult);

    // Should NOT overwrite existing certificate_id
    expect($domain->fresh()->certificate_id)->toBe($existingCertificate->id);
    expect($domain->fresh()->certificate_id)->not->toBe($newCertificate->id);
});

it('handles multiple domains in a single certificate', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
    ]);

    // Create primary domain
    $primaryDomain = $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => DomainRecordType::Primary,
        'certificate_id' => null,
    ]);

    // Create www domain
    $wwwDomain = $site->domainRecords()->create([
        'name' => 'www.example.com',
        'type' => DomainRecordType::Alias,
        'certificate_id' => null,
    ]);

    // Create certificate covering both domains
    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', 'www.example.com'],
        'active' => false,
    ]);

    // Enable SSL
    $job = new EnableSslJob($certificate);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');

    $mockResult = new ExecutionResult(
        output: 'SSL enabled successfully',
        exitCode: 0,
        duration: 3.0
    );

    $method->invoke($job, $mockResult);

    // Both domains should have the same certificate_id
    expect($primaryDomain->fresh()->certificate_id)->toBe($certificate->id);
    expect($wwwDomain->fresh()->certificate_id)->toBe($certificate->id);
});
