<?php

use Nip\Server\Enums\ProvisionScriptStatus;
use Nip\Server\Models\ProvisionScript;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Jobs\DeploySiteJob;
use Nip\Site\Models\Site;

it('creates provision script for deploy job', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::Deploying,
            'branch' => 'main',
        ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new DeploySiteJob($site);
    $job->handle($ssh);

    expect(ProvisionScript::query()->where('resource_type', 'site')->count())->toBe(1);

    $script = ProvisionScript::query()->where('resource_type', 'site')->first();

    expect($script->resource_id)->toBe($site->id)
        ->and($script->server_id)->toBe($server->id)
        ->and($script->status)->toBe(ProvisionScriptStatus::Completed);
});

it('generates deploy script with correct environment variables', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::Deploying,
            'domain' => 'example.com',
            'user' => 'netipar',
            'branch' => 'develop',
        ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new DeploySiteJob($site);
    $job->handle($ssh);

    $script = ProvisionScript::query()->where('resource_type', 'site')->first();

    expect($script->content)
        ->toContain('Deploying site example.com')
        ->toContain('export NIP_SITE_ROOT=')
        ->toContain('export NIP_RELEASES_PATH=')
        ->toContain('export NIP_SITE_PATH=')
        ->toContain('export NIP_SITE_BRANCH="develop"')
        ->toContain('export NIP_SITE_REPOSITORY=')
        ->toContain('export NIP_PHP=')
        ->toContain('export NIP_COMPOSER=')
        ->toContain('export NIP_RELEASE_NAME=')
        ->toContain('export NIP_NEW_RELEASE_PATH=');
});

it('includes laravel specific deploy commands for laravel sites', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::Deploying,
        ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new DeploySiteJob($site);
    $job->handle($ssh);

    $script = ProvisionScript::query()->where('resource_type', 'site')->first();

    expect($script->content)
        ->toContain('Zero-Downtime Deployment for Laravel')
        ->toContain('git clone')
        ->toContain('$NIP_COMPOSER install')
        ->toContain('$NIP_PHP artisan optimize')
        ->toContain('$NIP_PHP artisan migrate --force')
        ->toContain('npm')
        ->toContain('ln -sfn "$NIP_NEW_RELEASE_PATH" "$NIP_SITE_ROOT/current"');
});

it('updates deploy status to deployed on success', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::Deploying,
            'last_deployed_at' => null,
        ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new DeploySiteJob($site);
    $job->handle($ssh);

    $site->refresh();

    expect($site->deploy_status)->toBe(DeployStatus::Deployed)
        ->and($site->last_deployed_at)->not->toBeNull();
});

it('updates deploy status to failed on permanent failure', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::Deploying,
        ]);

    $job = new DeploySiteJob($site);
    $exception = new \Exception('Deployment failed');

    // Simulate the failed callback being called
    $job->failed($exception);

    $site->refresh();

    expect($site->deploy_status)->toBe(DeployStatus::Failed);
});

it('generates job tags correctly', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::Deploying,
        ]);

    $job = new DeploySiteJob($site);
    $tags = $job->tags();

    expect($tags)->toContain('provision')
        ->and($tags)->toContain('site')
        ->and($tags)->toContain("site:{$site->id}")
        ->and($tags)->toContain("server:{$server->id}")
        ->and($tags)->toContain('deploy');
});
