<?php

use Illuminate\Support\Facades\Queue;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Jobs\RenewCertificateJob;
use Nip\Domain\Models\Certificate;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Models\Site;

beforeEach(function () {
    Queue::fake();
});

it('copies expires_at and issued_at when creating a clone certificate', function () {
    $site = Site::factory()->create();
    $expiresAt = now()->addMonths(3);
    $issuedAt = now()->subDays(5);

    $source = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'path' => '/etc/nginx/ssl/example.com',
        'domains' => ['*.example.com', 'example.com'],
        'expires_at' => $expiresAt,
        'issued_at' => $issuedAt,
        'status' => CertificateStatus::Installed,
    ]);

    $cloneSite = Site::factory()->create();

    $clone = $cloneSite->certificates()->create([
        'type' => CertificateType::Clone,
        'source_certificate_id' => $source->id,
        'path' => $source->path,
        'domains' => ['alias.example.com'],
        'status' => CertificateStatus::Installed,
        'expires_at' => $source->expires_at,
        'issued_at' => $source->issued_at,
    ]);

    expect($clone->expires_at->toDateTimeString())->toBe($expiresAt->toDateTimeString());
    expect($clone->issued_at->toDateTimeString())->toBe($issuedAt->toDateTimeString());
});

it('updates clone certificates when source certificate is renewed', function () {
    $site = Site::factory()->create();
    $oldExpiresAt = now()->addDays(30);
    $oldIssuedAt = now()->subMonths(2);

    $source = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'path' => '/etc/nginx/ssl/example.com',
        'domains' => ['*.example.com', 'example.com'],
        'expires_at' => $oldExpiresAt,
        'issued_at' => $oldIssuedAt,
        'status' => CertificateStatus::Renewing,
    ]);

    $clone1 = Certificate::factory()->for(Site::factory())->create([
        'type' => CertificateType::Clone,
        'source_certificate_id' => $source->id,
        'path' => $source->path,
        'domains' => ['alias1.example.com'],
        'expires_at' => $oldExpiresAt,
        'issued_at' => $oldIssuedAt,
        'status' => CertificateStatus::Installed,
    ]);

    $clone2 = Certificate::factory()->for(Site::factory())->create([
        'type' => CertificateType::Clone,
        'source_certificate_id' => $source->id,
        'path' => $source->path,
        'domains' => ['alias2.example.com'],
        'expires_at' => $oldExpiresAt,
        'issued_at' => $oldIssuedAt,
        'status' => CertificateStatus::Installed,
    ]);

    // Simulate renewal job success
    $newExpiresAt = now()->addMonths(3);
    $job = new RenewCertificateJob($source);

    // Mock the execution result with certificate expiry info
    $mockOutput = "Certificate deployed successfully\nCertificate expires: " . $newExpiresAt->format('M d H:i:s Y') . " GMT";
    $result = new ExecutionResult($mockOutput, 0, 1.5);

    // Call handleSuccess via reflection
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');
    $method->invoke($job, $result);

    // Refresh models from database
    $source->refresh();
    $clone1->refresh();
    $clone2->refresh();

    // Source should be updated
    expect($source->status)->toBe(CertificateStatus::Installed);

    // Both clones should have the same expires_at as source
    expect($clone1->expires_at->toDateTimeString())->toBe($source->expires_at->toDateTimeString());
    expect($clone2->expires_at->toDateTimeString())->toBe($source->expires_at->toDateTimeString());
    expect($clone1->issued_at->toDateTimeString())->toBe($source->issued_at->toDateTimeString());
    expect($clone2->issued_at->toDateTimeString())->toBe($source->issued_at->toDateTimeString());
});
