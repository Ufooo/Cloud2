<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Nip\BackgroundProcess\Jobs\SyncBackgroundProcessJob;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;
use Nip\UnixUser\Models\UnixUser;

function renderCollisionScript(bool $isInitialInstall): string
{
    $process = BackgroundProcess::factory()->make([
        'id' => 32,
        'name' => 'Queue Worker',
        'command' => 'php8.4 artisan queue:work redis',
        'directory' => '/home/dev/example.com/current',
        'user' => 'dev',
    ]);

    return view('provisioning.scripts.background-process.sync', [
        'process' => $process,
        'processId' => $process->id,
        'name' => $process->name,
        'command' => $process->command,
        'directory' => $process->directory,
        'user' => $process->user,
        'processes' => 1,
        'startsecs' => 1,
        'stopwaitsecs' => 15,
        'stopsignal' => 'TERM',
        'isInitialInstall' => $isInitialInstall,
    ])->render();
}

it('refuses to overwrite a supervisor config it does not own', function () {
    // A program is named after the process id, so a config already sitting
    // under that name belongs to something else: a daemon installed by hand,
    // or one from another Cloud instance with an overlapping id space.
    // Overwriting it kills a running daemon without a trace - on 2026-08-31
    // this silently replaced office.netipar.dev's Reverb with a queue worker.
    $script = renderCollisionScript(isInitialInstall: true);

    expect($script)
        ->toContain('if [ -f "${CONFIG_FILE}" ]; then')
        ->toContain('exit 1');
});

it('explains the refusal in the form the failed-script alert reads', function () {
    // ProvisionScript::errorMessage() pulls the "[ERROR] ..." line out of the
    // output; without the brackets the alert falls back to the last line and
    // the refusal reads as an unexplained failure.
    $script = renderCollisionScript(isInitialInstall: true);

    expect($script)->toContain('[ERROR] Refusing to overwrite supervisor program ${PROGRAM_NAME}');

    $process = Nip\Server\Models\ProvisionScript::factory()->make([
        'status' => Nip\Server\Enums\ProvisionScriptStatus::Failed,
        'output' => "some earlier output\n[ERROR] Refusing to overwrite supervisor program netipar-32 - it belongs to another daemon.\n",
    ]);

    expect($process->error_message)
        ->toBe('Refusing to overwrite supervisor program netipar-32 - it belongs to another daemon.');
});

it('refuses before it changes anything on the server', function () {
    // A refused install must leave the machine exactly as it found it, so the
    // check comes before the log directory and the sudoers rule are touched.
    $script = renderCollisionScript(isInitialInstall: true);

    $guardPosition = strpos($script, 'if [ -f "${CONFIG_FILE}" ]; then');
    $mkdirPosition = strpos($script, 'mkdir -p "${LOG_DIR}"');
    $sudoersPosition = strpos($script, 'SUDOERS_FILE=');

    expect($guardPosition)->toBeLessThan($mkdirPosition)
        ->and($guardPosition)->toBeLessThan($sudoersPosition);
});

it('still rewrites the config when an existing process is updated', function () {
    // Updating a process the platform already owns must overwrite its config,
    // otherwise no setting could ever be changed.
    $script = renderCollisionScript(isInitialInstall: false);

    expect($script)
        ->not->toContain('if [ -f "${CONFIG_FILE}" ]; then')
        ->toContain('cat > "${CONFIG_FILE}"');
});

it('creates a site process as an initial install', function () {
    Queue::fake();

    $server = Server::factory()->connected()->create();
    $site = Site::factory()->for($server)->create();
    UnixUser::factory()->for($server)->create(['username' => 'dev']);

    $this->actingAs(User::factory()->create())
        ->post(route('sites.background-processes.store', $site), [
            'name' => 'Queue Worker',
            'command' => 'php8.4 artisan queue:work redis',
            'directory' => '/home/dev/example.com/current',
            'user' => 'dev',
            'processes' => 1,
            'startsecs' => 1,
            'stopwaitsecs' => 15,
            'stopsignal' => 'TERM',
        ]);

    Queue::assertPushed(
        SyncBackgroundProcessJob::class,
        fn (SyncBackgroundProcessJob $job) => $job->isInitialInstall === true
    );
});

it('updates a site process without the initial install guard', function () {
    Queue::fake();

    $server = Server::factory()->connected()->create();
    $site = Site::factory()->for($server)->create();
    UnixUser::factory()->for($server)->create(['username' => 'dev']);
    $process = BackgroundProcess::factory()->for($server)->for($site)->create(['user' => 'dev']);

    $this->actingAs(User::factory()->create())
        ->patch(route('sites.background-processes.update', [$site, $process]), [
            'name' => 'Queue Worker',
            'command' => 'php8.4 artisan queue:work redis --tries=3',
            'directory' => '/home/dev/example.com/current',
            'user' => 'dev',
            'processes' => 1,
            'startsecs' => 1,
            'stopwaitsecs' => 15,
            'stopsignal' => 'TERM',
        ]);

    Queue::assertPushed(
        SyncBackgroundProcessJob::class,
        fn (SyncBackgroundProcessJob $job) => $job->isInitialInstall === false
    );
});
