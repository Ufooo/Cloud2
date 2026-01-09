<?php

use App\Models\User;
use Nip\Server\Models\Server;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Models\Site;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::factory()->create();
});

it('allows update when site is installed', function () {
    $site = Site::factory()->for($this->server)->create(['status' => SiteStatus::Installed]);

    expect($this->user->can('update', $site))->toBeTrue()
        ->and($site->canBeUpdated($this->user))->toBeTrue();
});

it('denies update when site is not installed', function () {
    $site = Site::factory()->for($this->server)->create(['status' => SiteStatus::Installing]);

    expect($this->user->can('update', $site))->toBeFalse()
        ->and($site->canBeUpdated($this->user))->toBeFalse();
});

it('allows delete when site is not installing', function () {
    $site = Site::factory()->for($this->server)->create(['status' => SiteStatus::Installed]);

    expect($this->user->can('delete', $site))->toBeTrue()
        ->and($site->canBeDeleted($this->user))->toBeTrue();
});

it('denies delete when site is installing', function () {
    $site = Site::factory()->for($this->server)->create(['status' => SiteStatus::Installing]);

    expect($this->user->can('delete', $site))->toBeFalse()
        ->and($site->canBeDeleted($this->user))->toBeFalse();
});

it('allows deploy when site is installed and has repository', function () {
    $site = Site::factory()->for($this->server)->create([
        'status' => SiteStatus::Installed,
        'repository' => 'example/repo',
    ]);

    expect($this->user->can('deploy', $site))->toBeTrue()
        ->and($site->canBeDeployed($this->user))->toBeTrue();
});

it('denies deploy when site has no repository', function () {
    $site = Site::factory()->for($this->server)->create([
        'status' => SiteStatus::Installed,
        'repository' => null,
    ]);

    expect($this->user->can('deploy', $site))->toBeFalse()
        ->and($site->canBeDeployed($this->user))->toBeFalse();
});

it('denies deploy when site is not installed', function () {
    $site = Site::factory()->for($this->server)->create([
        'status' => SiteStatus::Installing,
        'repository' => 'example/repo',
    ]);

    expect($this->user->can('deploy', $site))->toBeFalse()
        ->and($site->canBeDeployed($this->user))->toBeFalse();
});

it('returns correct permissions data structure', function () {
    $site = Site::factory()->for($this->server)->create([
        'status' => SiteStatus::Installed,
        'repository' => 'example/repo',
    ]);

    $permissions = $site->getPermissions($this->user);

    expect($permissions)
        ->update->toBeTrue()
        ->delete->toBeTrue()
        ->deploy->toBeTrue();
});

it('returns correct permissions when site cannot be updated', function () {
    $site = Site::factory()->for($this->server)->create([
        'status' => SiteStatus::Installing,
        'repository' => null,
    ]);

    $permissions = $site->getPermissions($this->user);

    expect($permissions)
        ->update->toBeFalse()
        ->delete->toBeFalse()
        ->deploy->toBeFalse();
});
