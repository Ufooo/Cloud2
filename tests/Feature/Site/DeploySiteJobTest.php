<?php

use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Deployment\Models\Deployment;
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
    $deployment = Deployment::factory()->for($site)->create([
        'status' => DeploymentStatus::Deploying,
    ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new DeploySiteJob($site, $deployment);
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
    $deployment = Deployment::factory()->for($site)->create([
        'status' => DeploymentStatus::Deploying,
    ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new DeploySiteJob($site, $deployment);
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
        ->toContain('export NIP_PHP_FPM=')
        ->toContain('export NIP_COMPOSER=')
        // Zero-downtime vars are inside placeholder expansion
        ->toContain('NIP_RELEASE_NAME=')
        ->toContain('NIP_RELEASE_DIRECTORY=');
});

it('includes zero-downtime deployment for laravel sites', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::Deploying,
        ]);
    $deployment = Deployment::factory()->for($site)->create([
        'status' => DeploymentStatus::Deploying,
    ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new DeploySiteJob($site, $deployment);
    $job->handle($ssh);

    $script = ProvisionScript::query()->where('resource_type', 'site')->first();

    expect($script->content)
        // CREATE_RELEASE macro
        ->toContain('Creating new release')
        ->toContain('Cloning from')
        ->toContain('git clone')
        ->toContain('Linking environment file')
        ->toContain('Linking auth.json')
        ->toContain('Linking storage directories')
        // User deploy script
        ->toContain('$NIP_COMPOSER install')
        ->toContain('$NIP_PHP artisan optimize')
        ->toContain('$NIP_PHP artisan migrate --force')
        // ACTIVATE_RELEASE macro
        ->toContain('Activating release')
        ->toContain('ln -s "$NIP_RELEASE_DIRECTORY" "$NIP_SITE_ROOT/current-temp" && mv -Tf "$NIP_SITE_ROOT/current-temp" "$NIP_SITE_ROOT/current"')
        ->toContain('Purging old releases');
});

it('uses simple git pull for non-zero-downtime sites', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->create([
            'type' => \Nip\Site\Enums\SiteType::NextJs,
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::Deploying,
        ]);
    $deployment = Deployment::factory()->for($site)->create([
        'status' => DeploymentStatus::Deploying,
    ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new DeploySiteJob($site, $deployment);
    $job->handle($ssh);

    $script = ProvisionScript::query()->where('resource_type', 'site')->first();

    expect($script->content)
        ->toContain('Pulling latest changes')
        ->toContain('git pull origin')
        ->toContain('npm ci || npm install')
        ->toContain('npm run build')
        ->not->toContain('NIP_RELEASE_NAME')
        ->not->toContain('NIP_RELEASE_DIRECTORY')
        ->not->toContain('Activating release');
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
    $deployment = Deployment::factory()->for($site)->create([
        'status' => DeploymentStatus::Deploying,
        'started_at' => now(),
    ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new DeploySiteJob($site, $deployment);
    $job->handle($ssh);

    $site->refresh();
    $deployment->refresh();

    expect($site->deploy_status)->toBe(DeployStatus::Deployed)
        ->and($site->last_deployed_at)->not->toBeNull()
        ->and($deployment->status)->toBe(DeploymentStatus::Finished)
        ->and($deployment->ended_at)->not->toBeNull();
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
    $deployment = Deployment::factory()->for($site)->create([
        'status' => DeploymentStatus::Deploying,
        'started_at' => now(),
    ]);

    $job = new DeploySiteJob($site, $deployment);
    $exception = new \Exception('Deployment failed');

    // Simulate the failed callback being called
    $job->failed($exception);

    $site->refresh();
    $deployment->refresh();

    expect($site->deploy_status)->toBe(DeployStatus::Failed)
        ->and($deployment->status)->toBe(DeploymentStatus::Failed);
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
    $deployment = Deployment::factory()->for($site)->create([
        'status' => DeploymentStatus::Deploying,
    ]);

    $job = new DeploySiteJob($site, $deployment);
    $tags = $job->tags();

    expect($tags)->toContain('provision')
        ->and($tags)->toContain('site')
        ->and($tags)->toContain("site:{$site->id}")
        ->and($tags)->toContain("server:{$server->id}")
        ->and($tags)->toContain('deploy');
});

it('uses custom deploy script when set on site', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::Deploying,
            'deploy_script' => 'echo "Custom deploy script"',
        ]);
    $deployment = Deployment::factory()->for($site)->create([
        'status' => DeploymentStatus::Deploying,
    ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new DeploySiteJob($site, $deployment);
    $job->handle($ssh);

    $script = ProvisionScript::query()->where('resource_type', 'site')->first();

    expect($script->content)
        ->toContain('echo "Custom deploy script"')
        ->not->toContain('$NIP_COMPOSER install');
});
