<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Jobs\RemoveBackgroundProcessJob;
use Nip\BackgroundProcess\Jobs\SyncBackgroundProcessJob;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Models\Server;
use Nip\Site\Enums\DetectedPackage;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Models\Site;
use Nip\Site\Services\InertiaSSRService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::factory()->create();
    $this->site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create([
            'detected_packages' => [
                DetectedPackage::Laravel->value,
                DetectedPackage::Inertia->value,
            ],
        ]);
});

it('can enable inertia ssr via api', function () {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->postJson("/sites/{$this->site->slug}/enable-ssr");

    $response->assertRedirect();

    expect(BackgroundProcess::where('site_id', $this->site->id)
        ->where('name', InertiaSSRService::SSR_DAEMON_NAME)
        ->exists())->toBeTrue();

    Queue::assertPushed(SyncBackgroundProcessJob::class);
});

it('creates background process with correct command', function () {
    Queue::fake();

    $service = app(InertiaSSRService::class);
    $process = $service->enable($this->site);

    $expectedCommand = 'php'.$this->site->php_version?->version().' artisan inertia:start-ssr';

    expect($process->name)->toBe(InertiaSSRService::SSR_DAEMON_NAME)
        ->and($process->command)->toBe($expectedCommand)
        ->and($process->directory)->toBe($this->site->getCurrentPath())
        ->and($process->user)->toBe($this->site->user)
        ->and($process->site_id)->toBe($this->site->id)
        ->and($process->server_id)->toBe($this->server->id)
        ->and($process->status)->toBe(ProcessStatus::Installing);
});

it('uses site php version in ssr command', function () {
    Queue::fake();

    $this->site->update(['php_version' => 'php83']);
    $this->site->refresh();

    $service = app(InertiaSSRService::class);
    $process = $service->enable($this->site);

    expect($process->command)->toBe('php8.3 artisan inertia:start-ssr');
});

it('returns existing process when ssr already enabled', function () {
    Queue::fake();

    $service = app(InertiaSSRService::class);

    $firstProcess = $service->enable($this->site);
    $secondProcess = $service->enable($this->site);

    expect($firstProcess->id)->toBe($secondProcess->id)
        ->and(BackgroundProcess::where('site_id', $this->site->id)->count())->toBe(1);
});

it('can disable inertia ssr via api', function () {
    Queue::fake();

    $phpVersion = $this->site->php_version?->version();

    $process = BackgroundProcess::factory()
        ->for($this->server)
        ->installed()
        ->create([
            'site_id' => $this->site->id,
            'name' => InertiaSSRService::SSR_DAEMON_NAME,
            'command' => "php{$phpVersion} artisan inertia:start-ssr",
        ]);

    $response = $this->actingAs($this->user)
        ->postJson("/sites/{$this->site->slug}/disable-ssr");

    $response->assertRedirect();

    expect($process->fresh()->status)->toBe(ProcessStatus::Deleting);

    Queue::assertPushed(RemoveBackgroundProcessJob::class);
});

it('returns true when ssr is enabled', function () {
    BackgroundProcess::factory()
        ->for($this->server)
        ->installed()
        ->create([
            'site_id' => $this->site->id,
            'name' => InertiaSSRService::SSR_DAEMON_NAME,
        ]);

    $service = app(InertiaSSRService::class);

    expect($service->isEnabled($this->site))->toBeTrue();
});

it('returns false when ssr is not enabled', function () {
    $service = app(InertiaSSRService::class);

    expect($service->isEnabled($this->site))->toBeFalse();
});

it('returns false when ssr process is deleting', function () {
    BackgroundProcess::factory()
        ->for($this->server)
        ->create([
            'site_id' => $this->site->id,
            'name' => InertiaSSRService::SSR_DAEMON_NAME,
            'status' => ProcessStatus::Deleting,
        ]);

    $service = app(InertiaSSRService::class);

    expect($service->isEnabled($this->site))->toBeFalse();
});

it('returns false when ssr process has failed', function () {
    BackgroundProcess::factory()
        ->for($this->server)
        ->failed()
        ->create([
            'site_id' => $this->site->id,
            'name' => InertiaSSRService::SSR_DAEMON_NAME,
        ]);

    $service = app(InertiaSSRService::class);

    expect($service->isEnabled($this->site))->toBeFalse();
});

it('returns 403 when site is not installed', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->create([
            'status' => SiteStatus::Pending,
            'detected_packages' => [DetectedPackage::Inertia->value],
        ]);

    $response = $this->actingAs($this->user)
        ->postJson("/sites/{$site->slug}/enable-ssr");

    $response->assertForbidden();
});

it('returns 403 when inertia is not detected', function () {
    $site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create([
            'detected_packages' => [DetectedPackage::Laravel->value],
        ]);

    $response = $this->actingAs($this->user)
        ->postJson("/sites/{$site->slug}/enable-ssr");

    $response->assertForbidden();
});

it('returns 409 when trying to enable ssr that is already enabled', function () {
    BackgroundProcess::factory()
        ->for($this->server)
        ->installed()
        ->create([
            'site_id' => $this->site->id,
            'name' => InertiaSSRService::SSR_DAEMON_NAME,
        ]);

    $response = $this->actingAs($this->user)
        ->postJson("/sites/{$this->site->slug}/enable-ssr");

    $response->assertConflict();
});

it('returns 409 when trying to disable ssr that is not enabled', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/sites/{$this->site->slug}/disable-ssr");

    $response->assertConflict();
});

it('includes ssr enabled state in package details', function () {
    BackgroundProcess::factory()
        ->for($this->server)
        ->installed()
        ->create([
            'site_id' => $this->site->id,
            'name' => InertiaSSRService::SSR_DAEMON_NAME,
        ]);

    $this->site->refresh();

    $response = $this->actingAs($this->user)
        ->get("/sites/{$this->site->slug}");

    $response->assertOk();

    $pageProps = $response->viewData('page')['props'];
    $packageDetails = $pageProps['site']['packageDetails'];

    $inertiaPackage = collect($packageDetails)->firstWhere('value', DetectedPackage::Inertia->value);

    expect($inertiaPackage['isEnabled'])->toBeTrue()
        ->and($inertiaPackage['disableActionLabel'])->toBe('Disable SSR');
});

it('shows ssr as disabled in package details when not enabled', function () {
    $response = $this->actingAs($this->user)
        ->get("/sites/{$this->site->slug}");

    $response->assertOk();

    $pageProps = $response->viewData('page')['props'];
    $packageDetails = $pageProps['site']['packageDetails'];

    $inertiaPackage = collect($packageDetails)->firstWhere('value', DetectedPackage::Inertia->value);

    expect($inertiaPackage['isEnabled'])->toBeFalse();
});
