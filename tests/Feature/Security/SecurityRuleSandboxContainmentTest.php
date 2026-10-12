<?php

use Illuminate\Support\Facades\File;
use Tests\Feature\Security\Support\SandboxViolation;
use Tests\Feature\Security\Support\SecuritySyncSandbox;

/*
 * Tests of the test tool. SecuritySyncSandbox runs the rendered sync script for real, so it has to refuse what
 * it says it refuses. Every "attack" below aims at a SENTINEL that this test created outside the sandbox, or at
 * a path that cannot exist, never at anything real: even a broken guard can then only touch a file that exists
 * for exactly this purpose. The claims are the ones in the class docblock, no more.
 */

beforeEach(function () {
    if (! SecuritySyncSandbox::isAvailable()) {
        $this->markTestSkipped('The sandbox needs bash and the usual coreutils (it does not run on Windows).');
    }

    $this->box = SecuritySyncSandbox::create();

    // Two places outside the sandbox with a file in each: a plain directory and a lookalike sibling whose name
    // starts with the sandbox root ("<root>-evil"), which a naive prefix test would take for being inside.
    $this->outside = sys_get_temp_dir().'/security-sync-outside-'.bin2hex(random_bytes(4));
    $this->evil = $this->box->root.'-evil';

    foreach ([$this->outside, $this->evil] as $directory) {
        mkdir($directory);
        file_put_contents($directory.'/sentinel', "keep me\n");
    }
});

afterEach(function () {
    SecuritySyncSandbox::cleanup();
    File::deleteDirectory($this->outside);
    File::deleteDirectory($this->evil);
});

describe('before the script runs', function () {
    it('refuses a script that names a path outside the sandbox', function (string $command) {
        $script = str_replace(['{ROOT}', '{EVIL}'], [$this->box->root, $this->evil], $command);

        expect(fn () => $this->box->run($script))->toThrow(SandboxViolation::class, 'refusing to run it');
    })->with([
        // The commands are harmless on purpose: if a refusal ever stopped working, nothing would be hurt.
        'the filesystem root' => ['ls /'],
        'everything below the root' => ['ls /*'],
        'the root, quoted' => ['ls "/"'],
        'a system directory' => ['ls /tmp'],
        'an absolute path to a tool' => ['/bin/ls'],
        'a root that hides in an assignment' => ['DIR=/; ls $DIR'],
        'a root inside a command substitution' => ['echo $(ls /)'],
        'a redirect to a place that cannot exist' => ['ls > /nonexistent-gagarin-dir/out'],
        'one step above the sandbox' => ['ls {ROOT}/..'],
        'two steps up from a subdirectory' => ['ls {ROOT}/conf/../../..'],
        'a sibling that merely starts with the root' => ['ls {EVIL}/sentinel'],
        'a leftover /etc/ path that the rewrite of the conf root missed' => ['echo "/etc/passwd"'],
    ]);

    it('refuses a script whose data still points at /etc/, which the rewrite of the conf root missed', function () {
        // The path scan skips heredoc bodies, they are data (nginx config is full of paths). This one tripwire looks at
        // them: a body that still names /etc/ means the rewrite of /etc/nginx/netipar-conf did not cover everything.
        $script = "cat > \"\$SANDBOX_ROOT/data\" << 'DATA'\ninclude /etc/nginx/mime.types;\nDATA\n";

        expect(fn () => $this->box->run($script))->toThrow(SandboxViolation::class, '/etc/');
    });

    it('refuses the builtins that could get around the sandbox', function (string $command) {
        expect(fn () => $this->box->run($command))->toThrow(SandboxViolation::class, 'refusing to run it');
    })->with([
        'eval' => ['eval "ls"'],
        'exec' => ['exec ls'],
        'source' => ['source ./nothing'],
    ]);

    it('lets a script through that stays inside, /dev/null included', function () {
        $result = $this->box->run('echo ok > /dev/null; mkdir -p "'.$this->box->root.'/conf/../conf/x/"; echo inside');

        expect($result['exit'])->toBe(0)
            ->and($result['output'])->toBe("inside\n")
            ->and(is_dir($this->box->root.'/conf/x'))->toBeTrue();
    });
});

describe('while the script runs', function () {
    it('stops a command whose path is only worked out at run time', function (string $command) {
        $env = ['OUTSIDE' => $this->outside, 'EVIL' => $this->evil];

        expect(fn () => $this->box->run($command, $env))->toThrow(SandboxViolation::class, 'BLOCKED');

        // Nothing outside moved, vanished, changed mode or grew.
        expect(file_get_contents($this->outside.'/sentinel'))->toBe("keep me\n")
            ->and(substr(sprintf('%o', fileperms($this->outside.'/sentinel')), -3))->toBe('644')
            ->and(scandir($this->outside))->toBe(['.', '..', 'sentinel'])
            ->and(file_get_contents($this->evil.'/sentinel'))->toBe("keep me\n")
            ->and(scandir($this->evil))->toBe(['.', '..', 'sentinel']);
    })->with([
        'rm' => ['rm -f "$OUTSIDE/sentinel"'],
        'rm -rf of a directory' => ['rm -rf "$OUTSIDE"'],
        'rm through ..' => ['rm -f "$SANDBOX_ROOT/../$(basename "$OUTSIDE")/sentinel"'],
        'rm in a lookalike sibling of the root' => ['rm -f "${SANDBOX_ROOT}-evil/sentinel"'],
        'rm of a path that an empty variable turned absolute' => ['EMPTY=""; rm -f "$EMPTY/sentinel-in-the-filesystem-root"'],
        'chmod' => ['chmod 000 "$OUTSIDE/sentinel"'],
        'mv out of the sandbox' => ['touch inside; mv inside "$OUTSIDE/moved"'],
        'mv from outside' => ['mv "$OUTSIDE/sentinel" inside'],
        'cp out of the sandbox' => ['touch inside; cp inside "$OUTSIDE/copy"'],
        'cp from outside' => ['cp "$OUTSIDE/sentinel" inside'],
        'mkdir' => ['mkdir -p "$OUTSIDE/newdir"'],
        'touch' => ['touch "$OUTSIDE/newfile"'],
        'ln' => ['ln -s "$OUTSIDE/sentinel" link-to-outside'],
        'find -delete' => ['find "$OUTSIDE" -type f -delete'],
        // The file is made by a redirect on purpose: touch has a guard of its own and would hide a missing one on cd.
        'cd' => ['cd "$OUTSIDE"; echo hi > created-in-the-wrong-directory'],
    ]);

    it('stops at the first unset variable instead of turning it into an empty path', function () {
        // Without -u, "$UNSET"/* would be /*.
        $result = $this->box->run('touch before-the-mistake; rm -f "$NOWHERE_DEFINED"/*; touch after-the-mistake');

        expect($result['exit'])->not->toBe(0)
            ->and($result['error'])->toContain('NOWHERE_DEFINED')
            ->and(file_exists($this->box->root.'/before-the-mistake'))->toBeTrue()
            ->and(file_exists($this->box->root.'/after-the-mistake'))->toBeFalse();
    });

    it('has no way to reach the network or another machine', function (string $command) {
        $result = $this->box->run($command);

        expect($result['exit'])->toBe(127);
    })->with([
        'curl' => ['curl --version'],
        'ssh' => ['ssh -V'],
        'sudo' => ['sudo true'],
        'wget' => ['wget --version'],
    ]);

    it('lets the commands the sync script needs work on paths inside', function () {
        $result = $this->box->run(implode("\n", [
            'set -e',
            'mkdir -p "$SANDBOX_ROOT/conf/a" "$SANDBOX_ROOT/conf/b"',
            'touch "$SANDBOX_ROOT/conf/a/f"',
            'cp -p "$SANDBOX_ROOT/conf/a/f" "$SANDBOX_ROOT/conf/b/f"',
            'chmod 640 "$SANDBOX_ROOT/conf/b/f"',
            'mv -f "$SANDBOX_ROOT/conf/b/f" "$SANDBOX_ROOT/conf/b/g"',
            'find "$SANDBOX_ROOT/conf/b" -mindepth 1 -maxdepth 1 -type f ! -name \'x\' -delete',
            'rm -f "$SANDBOX_ROOT/conf/a/f"',
            'echo done',
        ]));

        expect($result['exit'])->toBe(0)
            ->and($result['output'])->toBe("done\n")
            ->and(file_exists($this->box->root.'/conf/b/g'))->toBeFalse();
    });
});
