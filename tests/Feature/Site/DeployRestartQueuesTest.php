<?php

use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Deployment\Models\Deployment;
use Nip\Server\Models\ProvisionScript;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Jobs\DeploySiteJob;
use Nip\Site\Models\Site;

function siteWithRestartQueuesDeployScript(Server $server, string $domain): Site
{
    return Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::Deploying,
            'domain' => $domain,
            'deploy_script' => '$RESTART_QUEUES()',
        ]);
}

function renderRestartQueuesDeployScript(Site $site): string
{
    $deployment = Deployment::factory()->for($site)->create([
        'status' => DeploymentStatus::Deploying,
    ]);

    $ssh = Mockery::mock(SSHService::class);
    $ssh->shouldReceive('setTimeout')->once()->andReturnSelf();
    $ssh->shouldReceive('connect')->once();
    $ssh->shouldReceive('executeScript')->once()->andReturn(new ExecutionResult('Success', 0, 1.0));
    $ssh->shouldReceive('disconnect')->once();

    (new DeploySiteJob($site, $deployment))->handle($ssh);

    return ProvisionScript::query()
        ->where('resource_type', 'site')
        ->where('resource_id', $site->id)
        ->latest('id')
        ->first()
        ->content;
}

it('never restarts every supervisor program on the server', function () {
    $server = Server::factory()->create();
    $site = siteWithRestartQueuesDeployScript($server, 'example.com');

    BackgroundProcess::factory()->create([
        'server_id' => $server->id,
        'site_id' => $site->id,
    ]);

    $script = renderRestartQueuesDeployScript($site);

    // `restart all` bounced every program on the box: on the live server a
    // single netipar.cloud deploy took down 17 groups, including three other
    // sites' Reverb servers and an unrelated tunnel process.
    expect($script)->not->toContain('supervisorctl restart all');
});

it('restarts only the background processes belonging to the deployed site', function () {
    $server = Server::factory()->create();
    $site = siteWithRestartQueuesDeployScript($server, 'example.com');
    $neighbour = siteWithRestartQueuesDeployScript($server, 'neighbour.com');

    $own = BackgroundProcess::factory()->create([
        'server_id' => $server->id,
        'site_id' => $site->id,
    ]);
    $foreign = BackgroundProcess::factory()->create([
        'server_id' => $server->id,
        'site_id' => $neighbour->id,
    ]);

    $script = renderRestartQueuesDeployScript($site);

    expect($script)
        ->toContain("netipar-{$own->id}:")
        ->not->toContain("netipar-{$foreign->id}:");
});

it('addresses supervisor by group name so the restart resolves', function () {
    $server = Server::factory()->create();
    $site = siteWithRestartQueuesDeployScript($server, 'example.com');

    $process = BackgroundProcess::factory()->create([
        'server_id' => $server->id,
        'site_id' => $site->id,
    ]);

    $script = renderRestartQueuesDeployScript($site);

    // process_name=%(program_name)s_%(process_num)02d makes every program a
    // group, so the bare name resolves to nothing: `supervisorctl restart
    // netipar-4` answers "ERROR (no such process)". The trailing colon
    // addresses the group and restarts its members.
    expect($script)->toContain("sudo supervisorctl restart netipar-{$process->id}:");
});

it('skips the supervisor restart when the site has no background processes', function () {
    $server = Server::factory()->create();
    $site = siteWithRestartQueuesDeployScript($server, 'example.com');

    $script = renderRestartQueuesDeployScript($site);

    expect($script)
        ->not->toContain('supervisorctl restart')
        ->toContain('artisan queue:restart');
});
