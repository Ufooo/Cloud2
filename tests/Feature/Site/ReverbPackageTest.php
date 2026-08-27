<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Jobs\RemoveBackgroundProcessJob;
use Nip\BackgroundProcess\Jobs\SyncBackgroundProcessJob;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Models\Server;
use Nip\Site\Enums\DetectedPackage;
use Nip\Site\Models\Site;
use Nip\Site\Services\ReverbService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::factory()->create();
    $this->site = Site::factory()
        ->for($this->server)
        ->laravel()
        ->installed()
        ->create([
            'detected_packages' => [
                DetectedPackage::Laravel->value => 'v13.0.0',
                DetectedPackage::Reverb->value => 'v1.11.0',
            ],
        ]);
});

function reverbProcessOn(Server $server, int $port): BackgroundProcess
{
    return BackgroundProcess::factory()->create([
        'server_id' => $server->id,
        'name' => 'Reverb',
        'command' => "php8.4 artisan reverb:start --no-interaction --port={$port}",
    ]);
}

it('starts reverb from the packages card', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->post("/sites/{$this->site->slug}/enable-reverb")
        ->assertRedirect();

    $process = BackgroundProcess::query()->where('site_id', $this->site->id)->first();

    expect($process)->not->toBeNull()
        ->and($process->name)->toBe(ReverbService::REVERB_DAEMON_NAME)
        ->and($process->command)->toContain('artisan reverb:start')
        ->and($process->command)->toContain('--no-interaction')
        ->and($process->user)->toBe($this->site->user)
        ->and($process->status)->toBe(ProcessStatus::Installing);

    Queue::assertPushed(SyncBackgroundProcessJob::class);
});

it('allocates the first free port on the server', function () {
    Queue::fake();

    // 8080 is taken by a neighbouring site on the same machine.
    reverbProcessOn($this->server, 8080);

    $this->actingAs($this->user)->post("/sites/{$this->site->slug}/enable-reverb");

    $process = BackgroundProcess::query()->where('site_id', $this->site->id)->first();

    expect($process->command)->toContain('--port=8081');
});

it('does not let another server block a port', function () {
    Queue::fake();

    // Ports are per machine; a busy 8080 elsewhere is irrelevant here.
    reverbProcessOn(Server::factory()->create(), 8080);

    $this->actingAs($this->user)->post("/sites/{$this->site->slug}/enable-reverb");

    $process = BackgroundProcess::query()->where('site_id', $this->site->id)->first();

    expect($process->command)->toContain('--port=8080');
});

it('keeps a port reserved while its process is being removed', function () {
    Queue::fake();

    // The supervisor program still exists until the removal script completes,
    // so handing the port to a second process would make it fail to bind.
    $process = reverbProcessOn($this->server, 8080);
    $process->update(['status' => ProcessStatus::Deleting]);

    $this->actingAs($this->user)->post("/sites/{$this->site->slug}/enable-reverb");

    $created = BackgroundProcess::query()->where('site_id', $this->site->id)->first();

    expect($created->command)->toContain('--port=8081');
});

it('refuses to start reverb when the package is not installed', function () {
    Queue::fake();

    $site = Site::factory()->for($this->server)->laravel()->installed()->create([
        'detected_packages' => [DetectedPackage::Laravel->value => 'v13.0.0'],
    ]);

    $this->actingAs($this->user)
        ->post("/sites/{$site->slug}/enable-reverb")
        ->assertForbidden();

    expect(BackgroundProcess::query()->where('site_id', $site->id)->exists())->toBeFalse();
});

it('refuses to start reverb twice', function () {
    Queue::fake();

    $this->actingAs($this->user)->post("/sites/{$this->site->slug}/enable-reverb");

    $this->actingAs($this->user)
        ->post("/sites/{$this->site->slug}/enable-reverb")
        ->assertStatus(409);

    expect(BackgroundProcess::query()->where('site_id', $this->site->id)->count())->toBe(1);
});

it('stops reverb from the packages card', function () {
    Queue::fake();

    $this->actingAs($this->user)->post("/sites/{$this->site->slug}/enable-reverb");

    $this->actingAs($this->user)
        ->post("/sites/{$this->site->slug}/disable-reverb")
        ->assertRedirect();

    $process = BackgroundProcess::query()->where('site_id', $this->site->id)->first();

    expect($process->status)->toBe(ProcessStatus::Deleting);

    Queue::assertPushed(RemoveBackgroundProcessJob::class);
});

it('recognises a reverb process that was created by hand', function () {
    // The badge and the stop button have to agree with each other, so the
    // lookup matches the command rather than the display name.
    $process = reverbProcessOn($this->server, 8080);
    $process->update(['site_id' => $this->site->id, 'name' => 'ws daemon']);

    expect(app(ReverbService::class)->isEnabled($this->site))->toBeTrue();
});

it('offers no start button for packages that cannot be started yet', function () {
    // A button wired to nothing is worse than no button: before this, pressing
    // "Start Horizon" silently enabled Inertia SSR instead.
    expect(DetectedPackage::Horizon->hasEnableAction())->toBeFalse()
        ->and(DetectedPackage::Octane->hasEnableAction())->toBeFalse()
        ->and(DetectedPackage::Reverb->hasEnableAction())->toBeTrue()
        ->and(DetectedPackage::Inertia->hasEnableAction())->toBeTrue();
});
