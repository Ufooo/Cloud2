<?php

use Illuminate\Support\Facades\Event;
use Nip\Server\Enums\ProvisionScriptStatus;
use Nip\Server\Models\ProvisionScript;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Enums\SiteProvisioningStep;
use Nip\Site\Events\SiteProvisioningStepChanged;
use Nip\Site\Jobs\Provisioning\ConfigureNginxJob;
use Nip\Site\Jobs\Provisioning\FinalizeSiteJob;
use Nip\Site\Models\Site;

beforeEach(function () {
    Event::fake();
});

it('creates provision script for configure nginx job', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create();

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new ConfigureNginxJob($site);
    $job->handle($ssh);

    expect(ProvisionScript::query()->where('resource_type', 'site')->count())->toBe(1);

    $script = ProvisionScript::query()->where('resource_type', 'site')->first();

    expect($script->resource_id)->toBe($site->id)
        ->and($script->server_id)->toBe($server->id)
        ->and($script->status)->toBe(ProvisionScriptStatus::Completed)
        ->and($script->content)->toContain('Configuring Nginx');
});

it('updates site provisioning step on successful job execution', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create();

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new ConfigureNginxJob($site);
    $job->handle($ssh);

    $site->refresh();

    expect($site->provisioning_step)->toBe(SiteProvisioningStep::ConfiguringNginx);
});

it('dispatches site provisioning step changed event', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create();

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new ConfigureNginxJob($site);
    $job->handle($ssh);

    Event::assertDispatched(SiteProvisioningStepChanged::class, function ($event) use ($site) {
        return $event->site->id === $site->id
            && $event->site->provisioning_step === SiteProvisioningStep::ConfiguringNginx;
    });
});

it('generates nginx configuration script correctly', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'domain' => 'example.com',
            'user' => 'netipar',
        ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new ConfigureNginxJob($site);
    $job->handle($ssh);

    $script = ProvisionScript::query()->where('resource_type', 'site')->first();

    expect($script->content)
        ->toContain('Configuring Nginx')
        ->and($script->content)->toContain('example.com')
        ->and($script->content)->toContain('/etc/nginx/sites-available/example.com');
});

it('finalize job marks site as installed on success', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create();

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    $job = new FinalizeSiteJob($site);
    $job->handle($ssh);

    $site->refresh();

    expect($site->status->value)->toBe('installed')
        ->and($site->provisioning_step)->toBe(SiteProvisioningStep::FinishingUp);
});

it('generates job tags correctly', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create();

    $job = new ConfigureNginxJob($site);
    $tags = $job->tags();

    expect($tags)->toContain('provision')
        ->and($tags)->toContain('site')
        ->and($tags)->toContain("site:{$site->id}")
        ->and($tags)->toContain("server:{$server->id}")
        ->and($tags)->toContain('step:ConfiguringNginx');
});
