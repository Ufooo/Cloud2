<?php

use Illuminate\Support\Facades\Queue;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Jobs\DeleteCertificateJob;
use Nip\Domain\Models\Certificate;
use Nip\Site\Models\Site;

beforeEach(function () {
    Queue::fake();
});

it('skips file deletion for clone certificates and deletes only the database record', function () {
    $site = Site::factory()->create();

    $source = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'path' => '/etc/nginx/ssl/example.com',
        'domains' => ['*.example.com', 'example.com'],
    ]);

    $clone = Certificate::factory()->for(Site::factory())->create([
        'type' => CertificateType::Clone,
        'source_certificate_id' => $source->id,
        'path' => '/etc/nginx/ssl/example.com',
        'domains' => ['*.example.com', 'example.com'],
        'status' => CertificateStatus::Removing,
    ]);

    $job = new DeleteCertificateJob($clone);

    // Should not call parent::handle (no SSH), just delete the record
    $job->handle(app(\Nip\Server\Services\SSH\SSHService::class));

    expect(Certificate::find($clone->id))->toBeNull();
    expect(Certificate::find($source->id))->not->toBeNull();
});

it('skips file deletion for source certificate when clones still exist', function () {
    $site = Site::factory()->create();

    $source = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'path' => '/etc/nginx/ssl/example.com',
        'domains' => ['*.example.com', 'example.com'],
        'status' => CertificateStatus::Removing,
    ]);

    $clone = Certificate::factory()->for(Site::factory())->create([
        'type' => CertificateType::Clone,
        'source_certificate_id' => $source->id,
        'path' => '/etc/nginx/ssl/example.com',
        'domains' => ['*.example.com', 'example.com'],
    ]);

    $job = new DeleteCertificateJob($source);

    $job->handle(app(\Nip\Server\Services\SSH\SSHService::class));

    expect(Certificate::find($source->id))->toBeNull();
    expect(Certificate::find($clone->id))->not->toBeNull();
});
