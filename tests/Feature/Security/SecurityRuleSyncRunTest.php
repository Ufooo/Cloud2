<?php

use Tests\Feature\Security\Support\SecuritySyncSandbox;

require_once __DIR__.'/Support/helpers.php';

/*
 * These tests run the RENDERED sync script for real inside a throw-away directory (SecuritySyncSandbox):
 * paths rewritten into a temp dir, nginx/service/chown replaced by stubs, PATH reduced to a short tool list,
 * `bash -u`, and guard functions that stop rm/mv/cp/find/chmod/mkdir/touch/ln/cd when an argument resolves
 * outside the temp dir. No job is dispatched and nothing connects to a server.
 *
 * That is containment against MISTAKES in a script we render ourselves, not a security boundary: redirection
 * targets built at run time and builtins other than cd are not checked, and symlinks are not followed. What
 * the sandbox really refuses is pinned by SecurityRuleSandboxContainmentTest, nothing more is claimed here.
 *
 * They exist because the points they cover are statements about files on disk after the script ran, which
 * no amount of string matching on the script can prove: that a failed `nginx -t` leaves the previous
 * auth.conf byte for byte in place, that nothing is written when the PHP socket is missing.
 */

beforeEach(function () {
    if (! SecuritySyncSandbox::isAvailable()) {
        $this->markTestSkipped('The sandbox needs bash and the usual coreutils (it does not run on Windows).');
    }
});

afterEach(fn () => SecuritySyncSandbox::cleanup());

function securityOldProtection(): string
{
    // Deliberately unlike anything the template renders, so "restored" and "rewritten" can never look alike.
    return "# written by the previous sync\nauth_basic \"Previous\";\nauth_basic_user_file /etc/nginx/netipar-conf/example.com/htpasswd/1;\n";
}

/**
 * Render the sync for a site-wide rule with the given name.
 *
 * @return array{0: string, 1: string} the script and the auth.conf body it writes
 */
function securitySyncScript(string $ruleName): array
{
    $site = securityScriptSite();
    securityScriptRule($site, ['name' => $ruleName]);

    $script = renderSecuritySync($site);

    return [$script, securityAuthConf($script)];
}

describe('when nginx rejects the new configuration', function () {
    it('leaves the previous auth.conf exactly as it was', function () {
        [$script] = securitySyncScript('BADCONFIG');
        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());

        $result = $box->run($script, ['NGINX_BAD_MARKER' => 'BADCONFIG']);

        expect($result['exit'])->toBe(1)
            ->and($box->read('server/auth.conf'))->toBe(securityOldProtection())
            ->and($box->reloaded())->toBeFalse();

        // nginx -t, restore, nginx -t again. And whatever the backup is called, it must not sit in server/:
        // nginx reads every file in there, so a copy next to auth.conf is a duplicate auth_basic and fails -t.
        expect($box->nginxTests())->toBe([['auth.conf'], ['auth.conf']]);
    });

    it('removes the rejected config when there was nothing to go back to', function () {
        [$script] = securitySyncScript('BADCONFIG');
        $box = SecuritySyncSandbox::create();

        $result = $box->run($script, ['NGINX_BAD_MARKER' => 'BADCONFIG']);

        expect($result['exit'])->toBe(1)
            ->and($box->exists('server/auth.conf'))->toBeFalse()
            ->and($box->reloaded())->toBeFalse()
            ->and($box->nginxTests())->toBe([['auth.conf'], []]);
    });

    it('keeps the previous auth.conf when the failure has nothing to do with this site', function () {
        // Something else on the server is broken, so nginx -t fails whatever this site's config says.
        [$script] = securitySyncScript('Staging');
        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());

        $result = $box->run($script, ['NGINX_ALWAYS_FAIL' => '1']);

        expect($result['exit'])->not->toBe(0)
            ->and($box->read('server/auth.conf'))->toBe(securityOldProtection())
            ->and($box->reloaded())->toBeFalse();
    });

    it('keeps the protection when nginx rejects the removal of the last rule', function () {
        // Taking the last rule away is a sync too. nginx -t can fail for reasons that have nothing to do with
        // it, and the protection that is active has to survive that exactly like it survives a rejected rule.
        $site = securityScriptSite();
        $script = renderSecuritySync($site);

        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());
        $box->put('htpasswd/1', "anna:\$2y\$05\$stillneeded\n");

        $result = $box->run($script, ['NGINX_ALWAYS_FAIL' => '1']);

        expect($result['exit'])->not->toBe(0)
            ->and($box->read('server/auth.conf'))->toBe(securityOldProtection())
            ->and($box->read('htpasswd/1'))->toBe("anna:\$2y\$05\$stillneeded\n")
            ->and($box->reloaded())->toBeFalse();
    });

    it('does not bring back a stale backup of an earlier, interrupted run', function () {
        [$script] = securitySyncScript('BADCONFIG');
        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());

        // An earlier run dies right after it wrote the new config, like a dropped SSH connection.
        $box->run($script, ['NGINX_KILL_PARENT' => '1']);

        $leftovers = array_values(array_filter($box->files(), fn (string $file) => str_contains($file, 'prev')));
        expect($leftovers)->not->toBeEmpty();

        // Later the site has no protection any more and the next sync is rejected by nginx. "Nothing to go
        // back to" means nothing: the old backup must not come back as if it were the previous config.
        unlink($box->confDir.'/server/auth.conf');

        $result = $box->run($script, ['NGINX_BAD_MARKER' => 'BADCONFIG']);

        expect($result['exit'])->toBe(1)
            ->and($box->exists('server/auth.conf'))->toBeFalse();
    });
});

describe('after a rejected sync', function () {
    it('leaves the password files as the sync wrote them: fail-closed, and nothing is cleaned up yet', function () {
        // Recorded behaviour, deliberately not a rollback of the password files. The previous auth.conf comes back
        // and still points at the same files: a rule whose password changed keeps the NEW hash (the site stays
        // protected, the next successful sync repairs the rule), and the file of a rule that was meant to go is
        // still there, because the clean-up only runs once nginx accepted the config and reloaded. Deleting it
        // earlier would leave the restored auth.conf pointing at a file that is gone.
        $site = securityScriptSite();
        $rule = securityScriptRule($site, ['name' => 'BADCONFIG'], ['anna' => 'new-password']);
        $script = renderSecuritySync($site);

        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());
        $box->put("htpasswd/{$rule->id}", "anna:\$2y\$05\$oldhash\n");
        $box->put('htpasswd/9999', "ghost:\$2y\$05\$stillreferenced\n");

        $result = $box->run($script, ['NGINX_BAD_MARKER' => 'BADCONFIG']);

        expect($result['exit'])->toBe(1)
            ->and($box->read('server/auth.conf'))->toBe(securityOldProtection())
            ->and($box->read("htpasswd/{$rule->id}"))->toBe(implode("\n", securityHtpasswdLines($script, $rule))."\n")
            ->and($box->read('htpasswd/9999'))->toBe("ghost:\$2y\$05\$stillreferenced\n");
    });
});

describe('when nginx accepts the new configuration', function () {
    it('replaces auth.conf, reloads nginx and leaves no backup behind', function () {
        [$script, $body] = securitySyncScript('Staging');
        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());

        $result = $box->run($script);

        expect($result['exit'])->toBe(0)
            ->and(rtrim((string) $box->read('server/auth.conf')))->toBe(rtrim($box->localize($body)))
            ->and($box->read('server/auth.conf'))->not->toBe(securityOldProtection())
            ->and($box->reloaded())->toBeTrue()
            ->and($box->nginxTests())->toBe([['auth.conf']]);

        // "On success the backup goes": nothing called prev may be left anywhere below the site's conf dir.
        expect(array_values(array_filter($box->files(), fn (string $file) => str_contains($file, 'prev'))))->toBe([]);
    });

    it('writes auth.conf on a site that had none', function () {
        [$script, $body] = securitySyncScript('Staging');
        $box = SecuritySyncSandbox::create();

        $result = $box->run($script);

        expect($result['exit'])->toBe(0)
            ->and(rtrim((string) $box->read('server/auth.conf')))->toBe(rtrim($box->localize($body)))
            ->and($box->reloaded())->toBeTrue()
            ->and(array_values(array_filter($box->files(), fn (string $file) => str_contains($file, 'prev'))))->toBe([]);
    });
});

describe('a php site without a php version', function () {
    it('stops with a message before it writes anything when a path rule needs the socket', function (string $state, string $path) {
        $site = securityScriptSite($state, ['php_version' => null]);
        securityScriptRule($site, ['name' => 'Admin area', 'path' => $path]);
        $script = renderSecuritySync($site);

        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());
        $box->put('htpasswd/7', "anna:\$2y\$05\$existingpasswordhash\n");
        $before = $box->files();

        $result = $box->run($script);

        expect($result['exit'])->toBe(1)
            ->and($result['output'].$result['error'])->toMatch('/php/i')
            ->and($box->files())->toBe($before)
            ->and($box->read('server/auth.conf'))->toBe(securityOldProtection())
            ->and($box->read('htpasswd/7'))->toBe("anna:\$2y\$05\$existingpasswordhash\n")
            ->and($box->calls())->toBe([]);
    })->with([
        'a directory on a laravel site' => ['laravel', '/admin'],
        'a directory on a wordpress site' => ['wordpress', '/wp-admin'],
        'a .php file, whose fastcgi sits directly in the exact block' => ['wordpress', '/wp-login.php'],
    ]);

    it('stops for a path rule that sits next to a site-wide rule as well', function () {
        $site = securityScriptSite('laravel', ['php_version' => null]);
        securityScriptRule($site, ['name' => 'Staging']);
        securityScriptRule($site, ['name' => 'Admin area', 'path' => '/admin']);

        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());
        $before = $box->files();

        $result = $box->run(renderSecuritySync($site));

        expect($result['exit'])->toBe(1)
            ->and($box->files())->toBe($before)
            ->and($box->read('server/auth.conf'))->toBe(securityOldProtection())
            ->and($box->calls())->toBe([]);
    });

    it('does not even create the directories on a fresh site', function () {
        $site = securityScriptSite('laravel', ['php_version' => null]);
        securityScriptRule($site, ['name' => 'Admin area', 'path' => '/admin']);

        $box = SecuritySyncSandbox::create();

        $result = $box->run(renderSecuritySync($site));

        expect($result['exit'])->toBe(1)
            ->and($box->files())->toBe([])
            ->and(is_dir($box->root.'/conf'))->toBeFalse();
    });

    it('still writes a site-wide rule, which never refers to the socket', function (string $state) {
        // The guard must not be wider than the problem: an imported site often has no php version at all, and
        // a rule for the whole site does not need one.
        $site = securityScriptSite($state, ['php_version' => null]);
        securityScriptRule($site, ['name' => 'Staging']);
        $script = renderSecuritySync($site);

        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());

        $result = $box->run($script);

        expect($result['exit'])->toBe(0)
            ->and(rtrim((string) $box->read('server/auth.conf')))->toBe(rtrim($box->localize(securityAuthConf($script))))
            ->and($box->read('server/auth.conf'))->toContain('auth_basic "Staging";')->not->toContain('fastcgi')
            ->and($box->reloaded())->toBeTrue();
    })->with([
        'a laravel site' => ['laravel'],
        'a wordpress site' => ['wordpress'],
    ]);

    it('still cleans up when the last rule is taken away', function () {
        $site = securityScriptSite('laravel', ['php_version' => null]);
        $script = renderSecuritySync($site);

        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());
        $box->put('htpasswd/1', "anna:\$2y\$05\$gone\n");

        $result = $box->run($script);

        expect($result['exit'])->toBe(0)
            ->and($box->exists('server/auth.conf'))->toBeFalse()
            ->and($box->exists('htpasswd/1'))->toBeFalse()
            ->and($box->reloaded())->toBeTrue();
    });

    it('does not stop a static site, which has no php version by design', function () {
        $site = securityScriptSite('static', ['php_version' => null]);
        securityScriptRule($site, ['name' => 'Private', 'path' => '/private']);
        $box = SecuritySyncSandbox::create();

        $result = $box->run(renderSecuritySync($site));

        expect($result['exit'])->toBe(0)
            ->and($box->exists('server/auth.conf'))->toBeTrue()
            ->and($box->reloaded())->toBeTrue();
    });
});

describe('the password files', function () {
    it('are written byte for byte, readable by owner and group only, and the stale ones are removed', function () {
        $site = securityScriptSite();
        $siteWide = securityScriptRule($site, ['name' => 'Staging'], ['anna' => 's3cret-pass']);
        $admin = securityScriptRule($site, ['name' => 'Admin area', 'path' => '/admin'], ['bela' => 'one', "o'neil&co" => 'two']);
        $script = renderSecuritySync($site);

        $box = SecuritySyncSandbox::create();
        $box->put('htpasswd/9999', "ghost:\$2y\$05\$stale\n");
        $box->put('server/other.conf', "# somebody else's include\n");

        $result = $box->run($script);

        expect($result['exit'])->toBe(0);

        foreach ([$siteWide, $admin] as $rule) {
            // The shell must write exactly what the quoted heredoc says: no expansion of $2y$05$... in it.
            expect($box->read("htpasswd/{$rule->id}"))->toBe(implode("\n", securityHtpasswdLines($script, $rule))."\n")
                ->and($box->read("htpasswd/{$rule->id}"))->toContain('$2y$05$')
                ->and($box->mode("htpasswd/{$rule->id}"))->toBe('640');
        }

        expect($box->exists('htpasswd/9999'))->toBeFalse()
            ->and($box->read('server/other.conf'))->toBe("# somebody else's include\n")
            ->and($box->files())->not->toContain('server/htpasswd/');

        // The password files live next to server/, never in it: nginx would read them as configuration.
        expect(array_values(array_filter($box->files(), fn (string $file) => str_starts_with($file, 'server/'))))
            ->toEqualCanonicalizing(['server/', 'server/auth.conf', 'server/other.conf']);
    });

    it('go together with auth.conf when the last rule is removed', function () {
        $site = securityScriptSite();
        $script = renderSecuritySync($site);

        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());
        $box->put('server/other.conf', "# somebody else's include\n");
        $box->put('htpasswd/1', "anna:\$2y\$05\$gone\n");
        $box->put('htpasswd/2', "bela:\$2y\$05\$gone\n");

        $result = $box->run($script);

        expect($result['exit'])->toBe(0)
            ->and($box->exists('server/auth.conf'))->toBeFalse()
            ->and($box->read('server/other.conf'))->toBe("# somebody else's include\n")
            ->and(array_values(array_filter($box->files(), fn (string $file) => str_starts_with($file, 'htpasswd/') && ! str_ends_with($file, '/'))))->toBe([])
            ->and($box->reloaded())->toBeTrue();
    });
});

/**
 * Every file below the site's conf dir with its content, to prove that a run changed nothing at all.
 *
 * @return array<string, string>
 */
function securitySandboxSnapshot(SecuritySyncSandbox $box): array
{
    $snapshot = [];

    foreach ($box->files() as $file) {
        $snapshot[$file] = str_ends_with($file, '/') ? '' : (string) $box->read($file);
    }

    return $snapshot;
}

describe('when the script is interrupted', function () {
    it('keeps the previous auth.conf when the write of the new one is cut off half way', function (string $signal) {
        // Writing straight into auth.conf leaves a truncated file in place, and the server's next reload (any job, any
        // site) loads it. The new config is therefore written beside server/ and moved into place in one step.
        [$script] = securitySyncScript('Staging');
        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());

        $box->run($script, ['CAT_INTERRUPT' => $signal]);

        expect($box->read('server/auth.conf'))->toBe(securityOldProtection())
            ->and($box->reloaded())->toBeFalse();

        // And nothing half-written sits where the include glob would read it.
        expect(array_values(array_filter($box->files(), fn (string $file) => str_starts_with($file, 'server/'))))
            ->toEqualCanonicalizing(['server/', 'server/auth.conf']);
    })->with([
        'SIGKILL, which no trap can answer' => ['KILL'],
        'SIGTERM' => ['TERM'],
    ]);

    it('puts the previous config back when it is stopped before nginx accepted the new one', function () {
        // The new config is already in place, untested. If the script dies here and leaves it, a bad config breaks
        // every later nginx -t on the server, whichever site it is for.
        [$script] = securitySyncScript('Staging');
        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());

        $result = $box->run($script, ['NGINX_INTERRUPT' => 'TERM']);

        expect($result['exit'])->not->toBe(0)
            ->and($box->read('server/auth.conf'))->toBe(securityOldProtection())
            ->and($box->reloaded())->toBeFalse();

        expect(array_values(array_filter($box->files(), fn (string $file) => str_starts_with($file, 'server/'))))
            ->toEqualCanonicalizing(['server/', 'server/auth.conf']);
    });

    it('takes the untested config away again when there was nothing to go back to', function () {
        // The same rule as for a config nginx rejects: with no previous config, the untested one goes.
        [$script] = securitySyncScript('Staging');
        $box = SecuritySyncSandbox::create();

        $result = $box->run($script, ['NGINX_INTERRUPT' => 'TERM']);

        expect($result['exit'])->not->toBe(0)
            ->and($box->exists('server/auth.conf'))->toBeFalse()
            ->and($box->reloaded())->toBeFalse();
    });

    it('carries on to the end when the session is hung up', function () {
        // An SSH session that dies sends SIGHUP. Left alone it would stop the script between the new config and its test;
        // ignoring it lets the sync finish.
        [$script, $body] = securitySyncScript('Staging');
        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());

        $result = $box->run($script, ['NGINX_INTERRUPT' => 'HUP']);

        expect($result['exit'])->toBe(0)
            ->and(rtrim((string) $box->read('server/auth.conf')))->toBe(rtrim($box->localize($body)))
            ->and($box->reloaded())->toBeTrue()
            ->and(array_values(array_filter($box->files(), fn (string $file) => str_contains($file, 'prev') || str_contains($file, '.new'))))->toBe([]);
    });

    it('does not leave an untested config behind when the output connection breaks', function (bool $hadPreviousConfig) {
        // The case the review describes: the SSH session dies while the script works, nobody reads its output any more and
        // the next echo fails. Measured (bash, set -e): ignoring SIGPIPE does not keep the script alive, because the
        // failed echo ("write error: Broken pipe") ends it through errexit either way. What makes the end safe is the
        // exit trap: the new config is already moved into place and has not been tested, and the trap puts the previous
        // state back. A synthetic SIGPIPE proves nothing here, PHP hands its ignored SIGPIPE on to the child.
        [$script] = securitySyncScript('Staging');
        $box = SecuritySyncSandbox::create();

        if ($hadPreviousConfig) {
            $box->put('server/auth.conf', securityOldProtection());
        }

        $exit = $box->runWithOutputDroppedAfter($script, 'Writing');

        expect($exit)->not->toBe(0)
            ->and($box->read('server/auth.conf'))->toBe($hadPreviousConfig ? securityOldProtection() : null)
            ->and($box->reloaded())->toBeFalse();
    })->with([
        'with a previous config' => [true],
        'on a site that had none' => [false],
    ]);

    it('stops at an unset variable even where nobody runs it under bash -u', function () {
        // The sandbox adds -u itself; on a server nobody does, so the script has to ask for it. One typo in a variable
        // name must not turn into an empty path in a script that runs as root.
        [$script] = securitySyncScript('Staging');
        $broken = (string) preg_replace('/^(set -[a-z]+)$/m', "$1\necho \"\$UNSET_ON_PURPOSE\"\ntouch ran-past-the-unset-variable", $script, 1, $count);

        expect($count)->toBe(1);

        $box = SecuritySyncSandbox::create();

        $result = $box->run($broken, nounset: false);

        expect($result['exit'])->not->toBe(0)
            ->and($result['error'])->toContain('UNSET_ON_PURPOSE')
            ->and(file_exists($box->root.'/ran-past-the-unset-variable'))->toBeFalse();
    });

    it('writes the new config beside server/ and starts the script with set -eu', function () {
        $script = renderSecuritySync(securityScriptScenario('a site-wide rule'));

        preg_match('/^cat\s*>\s*(\S+)\s*<<\s*\'AUTHEOF\'/m', $script, $opener);

        expect($opener)->not->toBeEmpty()
            ->and($opener[1])->not->toBe('"$AUTH_CONF"')
            ->and($opener[1])->not->toContain('/server/');

        // set -eu in front of everything that writes
        $set = preg_match('/^set -(?=[a-z]*e)(?=[a-z]*u)[a-z]+$/m', $script, $match, PREG_OFFSET_CAPTURE);

        expect($set)->toBe(1)
            ->and($match[0][1])->toBeLessThan(securityScriptCommandOffset($script, 'mkdir'));
    });
});

describe('when the script stops before it writes anything', function () {
    it('changes nothing at all, not even through its exit trap, while an earlier run left its backup behind', function (string $cause) {
        // A run that is killed after the new config is in place leaves auth.conf.prev behind. The next run then stops at
        // a guard; whatever it does on the way out must not restore or delete that leftover.
        $site = securityScriptSite();
        $rule = securityScriptRule($site, ['name' => 'Admin area', 'path' => '/admin']);

        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());
        $box->run(renderSecuritySync($site), ['NGINX_KILL_PARENT' => '1']);

        expect(array_values(array_filter($box->files(), fn (string $file) => str_contains($file, 'prev'))))->not->toBeEmpty();

        if ($cause === 'php socket') {
            $site->update(['php_version' => null]);
        } else {
            $rule->update(['path' => '/admin/']);
        }

        $before = securitySandboxSnapshot($box);

        $result = $box->run(renderSecuritySync($site->refresh()));

        expect($result['exit'])->toBe(1)
            ->and(securitySandboxSnapshot($box))->toBe($before);
    })->with([
        'a php site without a php version' => ['php socket'],
        'a stored path that is not canonical' => ['legacy path'],
    ]);

    it('refuses a stored path that is not canonical and leaves the site as it was', function (string $path) {
        $site = securityScriptSite();
        securityScriptRule($site, ['name' => 'Legacy', 'path' => $path]);

        $box = SecuritySyncSandbox::create();
        $box->put('server/auth.conf', securityOldProtection());
        $box->put('htpasswd/7', "anna:\$2y\$05\$existingpasswordhash\n");
        $before = securitySandboxSnapshot($box);

        $result = $box->run(renderSecuritySync($site));

        expect($result['exit'])->toBe(1)
            ->and($result['output'].$result['error'])->toMatch('/path/i')
            ->and(securitySandboxSnapshot($box))->toBe($before)
            ->and($box->calls())->toBe([]);
    })->with([
        'the trailing slash the old controller stored' => ['/admin/'],
        'an exact location that would out-rank the dotfile deny' => ['/.env'],
        'a dead location' => ['//admin'],
        'a line break' => ["/admin\n"],
    ]);
});
