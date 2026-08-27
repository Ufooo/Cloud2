<?php

use Nip\BackgroundProcess\Models\BackgroundProcess;

function renderSyncScript(string $user = 'bconsulting'): string
{
    $process = BackgroundProcess::factory()->make([
        'id' => 31,
        'name' => 'Inertia SSR',
        'command' => 'php8.4 artisan inertia:start-ssr',
        'directory' => '/home/'.$user.'/example.com',
        'user' => $user,
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
        'stopwaitsecs' => 5,
        'stopsignal' => 'TERM',
    ])->render();
}

it('grants the site user permission to control supervisor', function () {
    $script = renderSyncScript('bconsulting');

    // The deploy script restarts this program as the site user. Without the
    // sudo rule the restart fails with "a password is required", which the
    // deploy then swallows - the daemon keeps running the old code.
    expect($script)
        ->toContain('bconsulting ALL=NOPASSWD: /usr/bin/supervisorctl *')
        ->toContain('/etc/sudoers.d/supervisor');
});

it('never installs a sudoers file it has not validated', function () {
    $script = renderSyncScript();

    // A broken sudoers file locks every user out of sudo, so the rule is
    // written to a temporary file and only installed once visudo accepts it.
    expect($script)
        ->toContain('visudo -c -f')
        ->toContain('install -o root -g root -m 0440');
});

it('adds the sudoers rule only once', function () {
    $script = renderSyncScript('bconsulting');

    // Syncing a process is routine, so the rule must not pile up on repeats.
    expect($script)->toContain('grep -q "^bconsulting " ');
});

it('keeps showing what supervisorctl did during a deploy', function () {
    $script = view('provisioning.scripts.deploy.placeholders.restart-queues', [
        'supervisorGroups' => ['netipar-31:'],
    ])->render();

    // `2>/dev/null` hid a "sudo: a password is required" for months: the deploy
    // reported success while the daemon was never restarted.
    expect($script)
        ->toContain('sudo supervisorctl restart netipar-31:')
        ->not->toContain('supervisorctl restart netipar-31: 2>/dev/null')
        ->not->toContain('supervisorctl reread 2>/dev/null')
        ->not->toContain('supervisorctl update 2>/dev/null');
});
