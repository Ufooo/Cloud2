<?php

use Illuminate\Support\Facades\Event;
use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Deployment\Events\DeploymentUpdated;
use Nip\Deployment\Models\Deployment;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Events\SiteStatusUpdated;
use Nip\Site\Models\Site;

it('updates deployment status to finished on valid callback', function () {
    Event::fake([DeploymentUpdated::class, SiteStatusUpdated::class]);

    $site = Site::factory()->installed()->create([
        'deploy_status' => DeployStatus::Deploying,
    ]);

    $deployment = Deployment::factory()->create([
        'site_id' => $site->id,
        'status' => DeploymentStatus::Deploying,
        'callback_token' => 'test-callback-token-123',
    ]);

    $response = $this->postJson('/deploy/callback/test-callback-token-123');

    $response->assertOk()->assertJson(['success' => true]);

    $deployment->refresh();
    expect($deployment->status)->toBe(DeploymentStatus::Finished);
    expect($deployment->callback_token)->toBeNull();
    expect($deployment->ended_at)->not->toBeNull();

    $site->refresh();
    expect($site->deploy_status)->toBe(DeployStatus::Deployed);
    expect($site->last_deployed_at)->not->toBeNull();

    Event::assertDispatched(DeploymentUpdated::class);
    Event::assertDispatched(SiteStatusUpdated::class);
});

it('returns 404 for invalid callback token', function () {
    $response = $this->postJson('/deploy/callback/invalid-token');

    $response->assertNotFound()->assertJson(['error' => 'Invalid or expired callback token']);
});

it('returns 404 for already completed deployment', function () {
    $site = Site::factory()->installed()->create();

    $deployment = Deployment::factory()->create([
        'site_id' => $site->id,
        'status' => DeploymentStatus::Finished,
        'callback_token' => 'completed-deployment-token',
    ]);

    $response = $this->postJson('/deploy/callback/completed-deployment-token');

    $response->assertNotFound();
});

it('returns 404 for expired token on failed deployment', function () {
    $site = Site::factory()->installed()->create();

    $deployment = Deployment::factory()->create([
        'site_id' => $site->id,
        'status' => DeploymentStatus::Failed,
        'callback_token' => 'failed-deployment-token',
    ]);

    $response = $this->postJson('/deploy/callback/failed-deployment-token');

    $response->assertNotFound();
});

it('clears callback token after successful callback', function () {
    Event::fake();

    $site = Site::factory()->installed()->create([
        'deploy_status' => DeployStatus::Deploying,
    ]);

    $deployment = Deployment::factory()->create([
        'site_id' => $site->id,
        'status' => DeploymentStatus::Deploying,
        'callback_token' => 'one-time-token',
    ]);

    $this->postJson('/deploy/callback/one-time-token')->assertOk();

    $deployment->refresh();
    expect($deployment->callback_token)->toBeNull();

    $this->postJson('/deploy/callback/one-time-token')->assertNotFound();
});
