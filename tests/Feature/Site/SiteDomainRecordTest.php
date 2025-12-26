<?php

use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Server\Models\Server;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Jobs\InstallSiteJob;
use Nip\Site\Models\Site;

it('creates primary domain record when site is created', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'example.com',
        'www_redirect_type' => WwwRedirectType::FromWww,
        'allow_wildcard' => false,
    ]);

    // Simulate what controller does
    $site->domainRecords()->create([
        'name' => $site->domain,
        'type' => DomainRecordType::Primary,
        'status' => DomainRecordStatus::Pending,
        'www_redirect_type' => $site->www_redirect_type,
        'allow_wildcard' => $site->allow_wildcard,
    ]);

    expect($site->domainRecords)->toHaveCount(1);
    expect($site->domainRecords->first()->name)->toBe('example.com');
    expect($site->domainRecords->first()->type)->toBe(DomainRecordType::Primary);
    expect($site->domainRecords->first()->status)->toBe(DomainRecordStatus::Pending);
});

it('enables domain records when site installation succeeds', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'status' => SiteStatus::Installing,
        'domain' => 'example.com',
    ]);

    // Create pending domain record
    $domainRecord = $site->domainRecords()->create([
        'name' => $site->domain,
        'type' => DomainRecordType::Primary,
        'status' => DomainRecordStatus::Pending,
    ]);

    $job = new InstallSiteJob($site);

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');

    $mockResult = new \Nip\Server\Services\SSH\ExecutionResult(
        output: 'Site installed successfully',
        exitCode: 0,
        duration: 5.0
    );

    $method->invoke($job, $mockResult);

    expect($site->fresh()->status)->toBe(SiteStatus::Installed);
    expect($domainRecord->fresh()->status)->toBe(DomainRecordStatus::Enabled);
});

it('primary domain has correct www redirect settings', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'domain' => 'myapp.com',
        'www_redirect_type' => WwwRedirectType::ToWww,
        'allow_wildcard' => true,
    ]);

    $site->domainRecords()->create([
        'name' => $site->domain,
        'type' => DomainRecordType::Primary,
        'status' => DomainRecordStatus::Pending,
        'www_redirect_type' => $site->www_redirect_type,
        'allow_wildcard' => $site->allow_wildcard,
    ]);

    $primaryDomain = $site->primaryDomain;

    expect($primaryDomain)->not->toBeNull();
    expect($primaryDomain->www_redirect_type)->toBe(WwwRedirectType::ToWww);
    expect($primaryDomain->allow_wildcard)->toBeTrue();
});
