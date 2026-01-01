<?php

use App\Models\User;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Enums\DetectedPackage;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Models\Site;
use Nip\Site\Services\PackageDetectionService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::factory()->create();
});

it('detects laravel framework from composer.lock', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create();

    $composerLock = json_encode([
        'packages' => [
            ['name' => 'laravel/framework', 'version' => 'v11.0.0'],
            ['name' => 'guzzlehttp/guzzle', 'version' => '7.0.0'],
        ],
    ]);

    $sshMock = Mockery::mock(SSHService::class);
    $sshMock->shouldReceive('connect')->once()->andReturnSelf();
    $sshMock->shouldReceive('getFileContent')->once()->andReturn($composerLock);
    $sshMock->shouldReceive('disconnect')->once();

    $service = new PackageDetectionService($sshMock);
    $packages = $service->detectPackages($site);

    expect($packages)->toContain(DetectedPackage::Laravel->value)
        ->and($site->fresh()->detected_packages)->toContain(DetectedPackage::Laravel->value);
});

it('detects multiple laravel packages', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create();

    $composerLock = json_encode([
        'packages' => [
            ['name' => 'laravel/framework', 'version' => 'v11.0.0'],
            ['name' => 'laravel/horizon', 'version' => 'v5.0.0'],
            ['name' => 'inertiajs/inertia-laravel', 'version' => 'v1.0.0'],
            ['name' => 'laravel/pulse', 'version' => 'v1.0.0'],
        ],
    ]);

    $sshMock = Mockery::mock(SSHService::class);
    $sshMock->shouldReceive('connect')->once()->andReturnSelf();
    $sshMock->shouldReceive('getFileContent')->once()->andReturn($composerLock);
    $sshMock->shouldReceive('disconnect')->once();

    $service = new PackageDetectionService($sshMock);
    $packages = $service->detectPackages($site);

    expect($packages)
        ->toContain(DetectedPackage::Laravel->value)
        ->toContain(DetectedPackage::Horizon->value)
        ->toContain(DetectedPackage::Inertia->value)
        ->toContain(DetectedPackage::Pulse->value);
});

it('detects packages from dev dependencies', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create();

    $composerLock = json_encode([
        'packages' => [
            ['name' => 'laravel/framework', 'version' => 'v11.0.0'],
        ],
        'packages-dev' => [
            ['name' => 'laravel/telescope', 'version' => 'v5.0.0'],
        ],
    ]);

    $sshMock = Mockery::mock(SSHService::class);
    $sshMock->shouldReceive('connect')->once()->andReturnSelf();
    $sshMock->shouldReceive('getFileContent')->once()->andReturn($composerLock);
    $sshMock->shouldReceive('disconnect')->once();

    $service = new PackageDetectionService($sshMock);
    $packages = $service->detectPackages($site);

    expect($packages)
        ->toContain(DetectedPackage::Laravel->value)
        ->toContain(DetectedPackage::Telescope->value);
});

it('returns empty array when composer.lock is not found', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create();

    $sshMock = Mockery::mock(SSHService::class);
    $sshMock->shouldReceive('connect')->once()->andReturnSelf();
    $sshMock->shouldReceive('getFileContent')->once()->andReturn(null);
    $sshMock->shouldReceive('disconnect')->once();

    $service = new PackageDetectionService($sshMock);
    $packages = $service->detectPackages($site);

    expect($packages)->toBeEmpty();
});

it('returns empty array when composer.lock contains invalid json', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create();

    $sshMock = Mockery::mock(SSHService::class);
    $sshMock->shouldReceive('connect')->once()->andReturnSelf();
    $sshMock->shouldReceive('getFileContent')->once()->andReturn('not valid json');
    $sshMock->shouldReceive('disconnect')->once();

    $service = new PackageDetectionService($sshMock);
    $packages = $service->detectPackages($site);

    expect($packages)->toBeEmpty();
});

it('returns empty array when ssh connection fails', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create();

    $sshMock = Mockery::mock(SSHService::class);
    $sshMock->shouldReceive('connect')->once()->andThrow(new Exception('Connection failed'));

    $service = new PackageDetectionService($sshMock);
    $packages = $service->detectPackages($site);

    expect($packages)->toBeEmpty();
});

it('can detect packages via api endpoint', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create();

    $composerLock = json_encode([
        'packages' => [
            ['name' => 'laravel/framework', 'version' => 'v11.0.0'],
            ['name' => 'laravel/horizon', 'version' => 'v5.0.0'],
        ],
    ]);

    $sshMock = Mockery::mock(SSHService::class);
    $sshMock->shouldReceive('connect')->once()->andReturnSelf();
    $sshMock->shouldReceive('getFileContent')->once()->andReturn($composerLock);
    $sshMock->shouldReceive('disconnect')->once();

    $this->app->instance(SSHService::class, $sshMock);

    $response = $this->actingAs($this->user)
        ->postJson("/sites/{$site->slug}/detect-packages");

    $response->assertOk()
        ->assertJsonPath('packages', [
            DetectedPackage::Laravel->value,
            DetectedPackage::Horizon->value,
        ])
        ->assertJsonStructure([
            'packages',
            'packageDetails',
        ]);
});

it('returns forbidden for uninstalled sites', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->create(['status' => SiteStatus::Pending]);

    $response = $this->actingAs($this->user)
        ->postJson("/sites/{$site->slug}/detect-packages");

    $response->assertForbidden();
});

it('returns package details with metadata', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create([
            'detected_packages' => [
                DetectedPackage::Laravel->value,
                DetectedPackage::Horizon->value,
                DetectedPackage::Inertia->value,
            ],
        ]);

    $sshMock = Mockery::mock(SSHService::class);
    $service = new PackageDetectionService($sshMock);
    $details = $service->getPackageDetails($site);

    expect($details)->toHaveCount(3);

    $horizon = collect($details)->firstWhere('value', DetectedPackage::Horizon->value);
    expect($horizon)
        ->toMatchArray([
            'label' => 'Horizon',
            'hasEnableAction' => true,
            'enableActionLabel' => 'Start Horizon',
        ]);

    $laravel = collect($details)->firstWhere('value', DetectedPackage::Laravel->value);
    expect($laravel)
        ->toMatchArray([
            'label' => 'Laravel',
            'hasEnableAction' => false,
        ]);
});

it('enum provides correct composer package names', function () {
    expect(DetectedPackage::Laravel->composerPackage())->toBe('laravel/framework')
        ->and(DetectedPackage::Horizon->composerPackage())->toBe('laravel/horizon')
        ->and(DetectedPackage::Inertia->composerPackage())->toBe('inertiajs/inertia-laravel')
        ->and(DetectedPackage::Livewire->composerPackage())->toBe('livewire/livewire');
});

it('enum provides correct enable action info', function () {
    expect(DetectedPackage::Horizon->hasEnableAction())->toBeTrue()
        ->and(DetectedPackage::Horizon->enableActionLabel())->toBe('Start Horizon')
        ->and(DetectedPackage::Inertia->hasEnableAction())->toBeTrue()
        ->and(DetectedPackage::Inertia->enableActionLabel())->toBe('Enable SSR')
        ->and(DetectedPackage::Laravel->hasEnableAction())->toBeFalse()
        ->and(DetectedPackage::Laravel->enableActionLabel())->toBeNull();
});
