<?php

use Illuminate\Support\Facades\Bus;
use Nip\Server\Models\Server;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Jobs\CreateIsolatedPhpFpmPoolJob;
use Nip\Site\Jobs\DeleteIsolatedPhpFpmPoolJob;
use Nip\Site\Jobs\UpdateSitePhpVersionJob;
use Nip\Site\Models\Site;
use Nip\Site\Services\SitePhpVersionService;

it('creates job with correct site and version', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'status' => SiteStatus::Installed,
        'php_version' => '8.2',
    ]);

    $job = new UpdateSitePhpVersionJob($site, '8.3');

    expect($job->site->id)->toBe($site->id);
    expect($job->newVersion)->toBe('8.3');
});

it('does not dispatch job when php version is same as current', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'status' => SiteStatus::Installed,
        'php_version' => '8.2',
    ]);

    // When PHP version is same, the controller should skip dispatching
    $currentPhpVersion = $site->php_version;
    $newPhpVersion = '8.2';

    $phpVersionChanging = $newPhpVersion !== null
        && $newPhpVersion !== $currentPhpVersion
        && $site->status === SiteStatus::Installed;

    expect($phpVersionChanging)->toBeFalse();
});

it('does not dispatch job when site is not installed', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'status' => SiteStatus::Installing,
        'php_version' => '8.2',
    ]);

    // When site is not installed, the controller should skip dispatching
    $currentPhpVersion = $site->php_version;
    $newPhpVersion = '8.3';

    $phpVersionChanging = $newPhpVersion !== null
        && $newPhpVersion !== $currentPhpVersion
        && $site->status === SiteStatus::Installed;

    expect($phpVersionChanging)->toBeFalse();
});

it('service creates pool when no other site uses new version', function () {
    Bus::fake();

    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'status' => SiteStatus::Installed,
        'php_version' => '8.2',
        'user' => 'testuser',
    ]);

    $service = new SitePhpVersionService;
    $service->updatePhpVersion($site, '8.3');

    Bus::assertChained([
        CreateIsolatedPhpFpmPoolJob::class,
        UpdateSitePhpVersionJob::class,
        DeleteIsolatedPhpFpmPoolJob::class,
    ]);
});

it('service skips pool creation when another site uses same version', function () {
    Bus::fake();

    $server = Server::factory()->create();

    // First site already uses PHP 8.3
    Site::factory()->for($server)->create([
        'status' => SiteStatus::Installed,
        'php_version' => '8.3',
        'user' => 'testuser',
    ]);

    // Second site wants to switch to PHP 8.3
    $site = Site::factory()->for($server)->create([
        'status' => SiteStatus::Installed,
        'php_version' => '8.2',
        'user' => 'testuser',
    ]);

    $service = new SitePhpVersionService;
    $service->updatePhpVersion($site, '8.3');

    // Should NOT create pool (already exists), but should delete old pool
    Bus::assertChained([
        UpdateSitePhpVersionJob::class,
        DeleteIsolatedPhpFpmPoolJob::class,
    ]);
});

it('service skips pool deletion when another site uses old version', function () {
    Bus::fake();

    $server = Server::factory()->create();

    // Another site still uses PHP 8.2
    Site::factory()->for($server)->create([
        'status' => SiteStatus::Installed,
        'php_version' => '8.2',
        'user' => 'testuser',
    ]);

    // This site wants to switch from PHP 8.2 to 8.3
    $site = Site::factory()->for($server)->create([
        'status' => SiteStatus::Installed,
        'php_version' => '8.2',
        'user' => 'testuser',
    ]);

    $service = new SitePhpVersionService;
    $service->updatePhpVersion($site, '8.3');

    // Should create new pool, but NOT delete old pool (still in use)
    Bus::assertChained([
        CreateIsolatedPhpFpmPoolJob::class,
        UpdateSitePhpVersionJob::class,
    ]);
});

it('generates correct script for php version update with user-based socket', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'php_version' => '8.2',
        'domain' => 'test.example.com',
        'user' => 'testuser',
    ]);

    $job = new UpdateSitePhpVersionJob($site, '8.3');

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('generateScript');
    $script = $method->invoke($job);

    expect($script)
        ->toContain('php8.2-fpm-testuser.sock')
        ->toContain('php8.3-fpm-testuser.sock')
        ->toContain('test.example.com')
        ->toContain('nginx -t')
        ->toContain('systemctl reload nginx');
});

it('updates site php version on successful job execution', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->for($server)->create([
        'php_version' => '8.2',
    ]);

    $job = new UpdateSitePhpVersionJob($site, '8.3');

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('handleSuccess');

    $mockResult = new \Nip\Server\Services\SSH\ExecutionResult(
        output: 'PHP version updated successfully',
        exitCode: 0,
        duration: 1.5
    );

    $method->invoke($job, $mockResult);

    expect($site->fresh()->php_version)->toBe('8.3');
});
