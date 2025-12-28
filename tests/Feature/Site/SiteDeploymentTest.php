<?php

use Illuminate\Support\Facades\Queue;
use Nip\Server\Models\Server;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Jobs\DeploySiteJob;
use Nip\Site\Models\Site;

beforeEach(function () {
    $this->user = \App\Models\User::factory()->create();
});

it('can deploy an installed site', function () {
    Queue::fake();

    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::NeverDeployed,
        ]);

    $this->actingAs($this->user)
        ->post(route('sites.deploy', $site))
        ->assertRedirect(route('sites.show', $site))
        ->assertSessionHas('success', 'Deployment started.');

    Queue::assertPushed(DeploySiteJob::class, function ($job) use ($site) {
        return $job->site->id === $site->id;
    });

    $site->refresh();
    expect($site->deploy_status)->toBe(DeployStatus::Deploying);
});

it('cannot deploy site that is not installed', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installing,
        ]);

    $this->actingAs($this->user)
        ->post(route('sites.deploy', $site))
        ->assertForbidden();
});

it('requires user to be authenticated to deploy site', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
        ]);

    $this->post(route('sites.deploy', $site))
        ->assertRedirect(route('login'));
});
