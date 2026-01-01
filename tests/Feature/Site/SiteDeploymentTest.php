<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Nip\Deployment\Models\Deployment;
use Nip\Server\Models\Server;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Jobs\DeploySiteJob;
use Nip\Site\Models\Site;
use Nip\SourceControl\Models\SourceControl;

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
        ->assertRedirect()
        ->assertSessionHas('success', 'Deployment started.');

    Queue::assertPushed(DeploySiteJob::class, function ($job) use ($site) {
        return $job->site->id === $site->id;
    });

    $site->refresh();
    expect($site->deploy_status)->toBe(DeployStatus::Deploying);
    expect(Deployment::where('site_id', $site->id)->count())->toBe(1);
});

it('fetches and stores commit info from github when deploying', function () {
    Queue::fake();
    Http::fake([
        'api.github.com/repos/owner/repo/commits/main' => Http::response([
            'sha' => 'abc123def456789',
            'commit' => [
                'message' => 'feat: add new feature',
                'author' => [
                    'name' => 'John Doe',
                ],
            ],
            'author' => [
                'login' => 'johndoe',
            ],
        ]),
    ]);

    $sourceControl = SourceControl::factory()->create([
        'user_id' => $this->user->id,
        'token' => 'fake-token',
    ]);

    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->for($sourceControl)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
            'deploy_status' => DeployStatus::NeverDeployed,
            'repository' => 'owner/repo',
            'branch' => 'main',
        ]);

    $this->actingAs($this->user)
        ->post(route('sites.deploy', $site))
        ->assertRedirect()
        ->assertSessionHas('success', 'Deployment started.');

    $deployment = Deployment::where('site_id', $site->id)->first();

    expect($deployment->commit_hash)->toBe('abc123def456789')
        ->and($deployment->commit_message)->toBe('feat: add new feature')
        ->and($deployment->commit_author)->toBe('John Doe')
        ->and($deployment->branch)->toBe('main');
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

it('can view deployment details', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
        ]);

    $deployment = \Nip\Deployment\Models\Deployment::factory()
        ->for($site)
        ->for($this->user)
        ->create([
            'output' => 'Test deployment output',
        ]);

    $this->actingAs($this->user)
        ->get(route('sites.deployments.show', [$site, $deployment]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sites/deployments/Show')
            ->has('site')
            ->has('deployment')
        );
});

it('cannot view deployment from another site', function () {
    $server = Server::factory()->create();
    $site = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
        ]);

    $otherSite = Site::factory()
        ->for($server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Installed,
        ]);

    $deployment = \Nip\Deployment\Models\Deployment::factory()
        ->for($otherSite)
        ->for($this->user)
        ->create();

    $this->actingAs($this->user)
        ->get(route('sites.deployments.show', [$site, $deployment]))
        ->assertNotFound();
});
