<?php

use Nip\Security\Database\Factories\SecurityRuleCredentialFactory;
use Nip\Security\Database\Factories\SecurityRuleFactory;
use Nip\Security\Models\SecurityRule;
use Symfony\Component\Process\Process;

require_once __DIR__.'/Support/helpers.php';

/*
 * Render tests for provisioning/scripts/security/sync.blade.php. Nothing here dispatches a job or
 * opens an SSH connection: the view is rendered with real rows, the way the jobs feed it, and the
 * resulting text is inspected (and, for syntax only, handed to `bash -n`, which never executes it).
 */

it('keeps htpasswd/ next to server/ and addresses everything by the site domain', function () {
    $site = securityScriptSite('laravel', ['domain' => 'shop.example.org']);
    securityScriptRule($site);

    $script = renderSecuritySync($site);

    // PHP drops a leading "#!" line of a compiled view (the redirect template loses its shebang that way), so
    // the template keeps its @php helpers above the shebang. Without that trick this assertion fails.
    expect($script)
        ->toStartWith('#!/bin/bash')
        ->toMatch('/^set -[a-z]*e[a-z]*$/m')
        ->toContain('SITE_CONF_DIR="/etc/nginx/netipar-conf/shop.example.org"')
        ->toContain('AUTH_CONF="$SITE_CONF_DIR/server/auth.conf"')
        ->toContain('HTPASSWD_DIR="$SITE_CONF_DIR/htpasswd"')
        ->toMatch('#mkdir -p[^\n]*\$SITE_CONF_DIR/server#')
        ->toMatch('#mkdir -p[^\n]*\$\{?HTPASSWD_DIR#');
});

it('protects a whole site with server-level auth_basic and no location block', function (string $state) {
    $site = securityScriptSite($state);
    $rule = securityScriptRule($site, ['name' => 'Staging', 'path' => null]);

    $parsed = securityNginxParse(securityAuthConf(renderSecuritySync($site)));

    expect($parsed['rest'])
        ->toContain('auth_basic "Staging";')
        ->toContain("auth_basic_user_file /etc/nginx/netipar-conf/example.com/htpasswd/{$rule->id};")
        ->and($parsed['blocks'])->toBeEmpty();
})->with([
    'a php site' => ['laravel'],
    'a static site' => ['static'],
]);

it('points auth_basic_user_file into htpasswd/ and never into server/', function (string $state, ?string $path) {
    // The server/ directory is read by nginx as configuration (include .../server/*), so a htpasswd
    // file in there would make `nginx -t` fail. The path also has to be absolute: the heredoc is
    // quoted, so a shell variable would reach nginx verbatim.
    $site = securityScriptSite($state);
    $rule = securityScriptRule($site, ['path' => $path]);

    $conf = securityAuthConf(renderSecuritySync($site));

    preg_match_all('/^[ \t]*auth_basic_user_file[ \t]+([^;]+);/m', $conf, $directives);

    expect($directives[1])->not->toBeEmpty();

    foreach ($directives[1] as $file) {
        expect($file)
            ->toBe("/etc/nginx/netipar-conf/example.com/htpasswd/{$rule->id}")
            ->not->toContain('/server/')
            ->not->toContain('$');
    }
})->with([
    'a site-wide rule on a php site' => ['laravel', null],
    'a path rule on a php site' => ['laravel', '/admin'],
    'a site-wide rule on a static site' => ['static', null],
    'a path rule on a static site' => ['static', '/private'],
]);

it('writes the htpasswd lines through a quoted heredoc so the bcrypt hash reaches the file untouched', function () {
    $site = securityScriptSite();
    $rule = securityScriptRule($site, [], ['anna' => 's3cret-pass', 'bela' => 'another-pass']);
    $hashes = $rule->credentials->pluck('password', 'username');

    $script = renderSecuritySync($site);

    // An unquoted heredoc would let the shell expand $2y, $05 and $<salt...> inside these lines.
    expect($hashes['anna'])->toStartWith('$2y$05$');

    expect($script)
        ->toContain("<< 'HTPASSWDEOF{$rule->id}'")
        ->toMatch('/^HTPASSWDEOF'.$rule->id.'$/m')
        ->toContain("anna:{$hashes['anna']}")
        ->toContain("bela:{$hashes['bela']}")
        ->toContain('chown root:netipar')
        ->toContain('chmod 640');
});

it('quotes every heredoc delimiter and terminates each of them', function (string $scenario) {
    $script = renderSecuritySync(securityScriptScenario($scenario));

    preg_match_all('/<<-?[ \t]*(\S+)/', $script, $openers);

    expect($openers[1])->not->toBeEmpty();

    foreach ($openers[1] as $delimiter) {
        expect($delimiter)->toMatch("/^'[A-Za-z0-9_]+'$/");
        expect($script)->toMatch('/^'.preg_quote(trim($delimiter, "'"), '/').'$/m');
    }
})->with(securityScriptScenarios(withEmpty: false));

it('writes one htpasswd file per rule plus a single auth.conf', function () {
    $site = securityScriptScenario('several rules');
    $ids = $site->securityRules()->pluck('id');

    $script = renderSecuritySync($site);

    preg_match_all("/<<[ \t]*'(HTPASSWDEOF\d+|AUTHEOF)'/", $script, $openers);

    expect($openers[1])->toHaveCount($ids->count() + 1)
        ->and(array_count_values($openers[1])['AUTHEOF'])->toBe(1);

    foreach ($ids as $id) {
        expect($openers[1])->toContain("HTPASSWDEOF{$id}");
    }
});

it('renders a script that bash can parse', function (string $scenario) {
    // `bash -n` only parses, nothing is executed. It catches what Blade's @if/@foreach can easily get
    // wrong around shell if/fi. Unterminated heredocs are NOT reliably caught (bash 3.2 stays silent
    // even on stderr), which is why the delimiter test above checks the terminators itself.
    $process = new Process(['bash', '-n']);
    $process->setInput(renderSecuritySync(securityScriptScenario($scenario)));
    $process->run();

    expect($process->getErrorOutput())->toBe('')
        ->and($process->getExitCode())->toBe(0);
})->with(securityScriptScenarios());

it('escapes the realm for nginx without turning it into HTML entities', function () {
    // Blade's {{ }} would print &quot; and &#039; here; in a bash/nginx file that is wrong.
    $site = securityScriptSite();
    securityScriptRule($site, ['name' => 'Bob\'s "R&D" \\ <area>']);

    expect(securityAuthConf(renderSecuritySync($site)))
        ->toContain('auth_basic "Bob\'s \\"R&D\\" \\\\ <area>";')
        ->not->toContain('&quot;')
        ->not->toContain('&#039;')
        ->not->toContain('&amp;')
        ->not->toContain('&lt;');
});

it('keeps the realm on one line and free of nginx variables', function (string $name) {
    // nginx expands "$word" inside auth_basic and fails `nginx -t` on an unknown variable; the sync
    // then drops auth.conf, so one odd name would silently take the site's protection away. A line
    // break must not be able to end the directive line either.
    $site = securityScriptSite();
    securityScriptRule($site, ['name' => $name]);

    $conf = securityAuthConf(renderSecuritySync($site));

    preg_match_all('/^auth_basic[ \t]+"(.*)";$/m', $conf, $realms);

    expect($realms[1])->toHaveCount(1)
        ->and($realms[1][0])->not->toContain('$')
        ->and(securityNginxParse($conf)['blocks'])->toBeEmpty();
})->with([
    'a variable' => ['Admin $area'],
    'a variable in braces' => ['Price ${amount}'],
    'a line break before a directive' => ["Staging\nlocation / { return 200; }"],
    'a carriage return' => ["Staging\rreturn 200;"],
]);

it('does not let a rule name or a username close a heredoc early', function () {
    // The heredoc terminators are lines of their own. A value that carries a line break followed by the
    // terminator would end the heredoc, and everything after it would run as root on the server.
    $site = securityScriptSite();
    $rule = SecurityRuleFactory::new()->for($site)->create(['name' => "Staging\nAUTHEOF\ntouch /tmp/injected-by-name"]);
    SecurityRuleCredentialFactory::new()->for($rule)->create([
        'username' => "anna\nHTPASSWDEOF{$rule->id}\ntouch /tmp/injected-by-username",
    ]);

    $script = renderSecuritySync($site);

    expect(preg_match_all('/^AUTHEOF$/m', $script))->toBe(1)
        ->and(preg_match_all('/^HTPASSWDEOF'.$rule->id.'$/m', $script))->toBe(1)
        ->and($script)->not->toMatch('/^[ \t]*touch /m');
});

it('writes usernames to the htpasswd file exactly as they were entered', function () {
    $site = securityScriptSite();
    $rule = securityScriptRule($site, [], ["o'neil&co" => 'pw']);
    $hash = $rule->credentials->sole()->password;

    expect(renderSecuritySync($site))
        ->toContain("o'neil&co:{$hash}")
        ->not->toContain('&#039;')
        ->not->toContain('&amp;');
});

it('writes exactly one htpasswd line per credential, even for usernames with line breaks', function () {
    // The heredoc is closed by a line of its own and every line is one user: a username that carries a
    // line break must neither end the heredoc early nor smuggle in an extra entry.
    $site = securityScriptSite();
    $rule = securityScriptRule($site, [], ["bob\nadmin:\$apr1\$evil" => 'pw', 'anna' => 'pw']);

    $lines = securityHtpasswdLines(renderSecuritySync($site), $rule);

    expect($lines)->toHaveCount(2)
        ->and($lines[1])->toStartWith('anna:');
});

it('protects a path on a php site with an exact and a prefix location that keeps .php behind fastcgi', function (string $state, string $path) {
    $site = securityScriptSite($state);
    $rule = securityScriptRule($site, ['name' => 'Admin area', 'path' => $path]);
    $userFile = "auth_basic_user_file /etc/nginx/netipar-conf/example.com/htpasswd/{$rule->id};";

    $parsed = securityNginxParse(securityAuthConf(renderSecuritySync($site)));

    // `= /p` catches the bare path; `^~ /p/` (with the slash) the subtree without swallowing
    // siblings such as /administrator. Neither alone is enough.
    expect(array_keys($parsed['blocks']))->toEqualCanonicalizing(["= {$path}", "^~ {$path}/"]);

    foreach ($parsed['blocks'] as $block) {
        expect($block)
            ->toContain('auth_basic "Admin area";')
            ->toContain($userFile)
            ->toContain('try_files $uri $uri/ /index.php?$query_string;');
    }

    // nginx refuses a location inside an exact-match location: `nginx -t` fails with
    // "location ... cannot be inside the exact location" (measured with nginx 1.25.4).
    expect(securityNginxParse($parsed['blocks']["= {$path}"])['blocks'])->toBeEmpty();

    // `^~` stops regex matching, so the site's own `location ~ \.php$` never runs for URLs under the
    // path; without a handler of its own every .php file in there is served as raw source code
    // (measured: an authenticated GET returns the file's PHP source).
    $nested = securityNginxParse($parsed['blocks']["^~ {$path}/"])['blocks'];

    // R2-4: the dotfile deny first, the php handler second. The first matching regex location wins, so the
    // order is part of the contract (it has its own test below as well).
    expect(array_keys($nested))->toBe(['~ /\.(?!well-known).*', '~ \.php$']);
    expect($nested['~ \.php$'])
        ->toContain('auth_basic "Admin area";')
        ->toContain($userFile)
        ->toContain('fastcgi_split_path_info ^(.+\.php)(/.+)$;')
        ->toContain('fastcgi_pass unix:'.$site->getPhpSocketPath().';')
        ->toContain('fastcgi_index index.php;')
        ->toContain('include fastcgi_params;')
        ->toContain('include netipar_fastcgi_defaults;');
})->with([
    'a laravel site' => ['laravel', '/admin'],
    'a wordpress site' => ['wordpress', '/wp-admin'],
    'a nested path' => ['laravel', '/admin/users'],
    'every character the path format allows' => ['laravel', '/my-app_v2.0/~user'],
    'a name that merely contains php' => ['laravel', '/phpmyadmin'],
    'a name that merely contains .php' => ['laravel', '/old.php.bak'],
]);

it('points the nested fastcgi_pass at the php-fpm socket of the site', function () {
    $site = securityScriptSite('laravel', ['php_version' => 'php84', 'user' => 'deploy']);
    securityScriptRule($site, ['path' => '/admin']);

    expect(securityAuthConf(renderSecuritySync($site)))
        ->toContain('fastcgi_pass unix:/var/run/php/php8.4-fpm-deploy.sock;');
});

it('uses a plain 404 fallback and no fastcgi for a path on a static site', function () {
    $site = securityScriptSite('static');
    $rule = securityScriptRule($site, ['name' => 'Private', 'path' => '/private']);

    $parsed = securityNginxParse(securityAuthConf(renderSecuritySync($site)));

    expect(array_keys($parsed['blocks']))->toEqualCanonicalizing(['= /private', '^~ /private/']);

    foreach ($parsed['blocks'] as $block) {
        expect($block)
            ->toContain('auth_basic "Private";')
            ->toContain("auth_basic_user_file /etc/nginx/netipar-conf/example.com/htpasswd/{$rule->id};")
            ->toContain('try_files $uri $uri/ =404;')
            ->not->toContain('fastcgi')
            ->not->toContain('index.php');
    }

    // Nothing may be nested in the exact location; the prefix location only carries the dotfile deny
    // (a static site has no php handler to put next to it).
    expect(securityNginxParse($parsed['blocks']['= /private'])['blocks'])->toBeEmpty()
        ->and(array_keys(securityNginxParse($parsed['blocks']['^~ /private/'])['blocks']))->toBe(['~ /\.(?!well-known).*']);
});

it('wires every rule of a site to its own htpasswd file', function () {
    $site = securityScriptSite();
    $siteWide = securityScriptRule($site, ['name' => 'Staging']);
    $admin = securityScriptRule($site, ['name' => 'Admin area', 'path' => '/admin'], ['anna' => 'one']);
    $api = securityScriptRule($site, ['name' => 'Api', 'path' => '/api/private'], ['carl' => 'two']);

    $parsed = securityNginxParse(securityAuthConf(renderSecuritySync($site)));

    $file = fn (SecurityRule $rule) => "auth_basic_user_file /etc/nginx/netipar-conf/example.com/htpasswd/{$rule->id};";

    expect($parsed['rest'])
        ->toContain('auth_basic "Staging";')
        ->toContain($file($siteWide))
        ->not->toContain($file($admin))
        ->not->toContain($file($api));

    expect(array_keys($parsed['blocks']))->toEqualCanonicalizing([
        '= /admin', '^~ /admin/', '= /api/private', '^~ /api/private/',
    ]);

    foreach (['= /admin', '^~ /admin/'] as $header) {
        expect($parsed['blocks'][$header])
            ->toContain($file($admin))
            ->not->toContain($file($siteWide))
            ->not->toContain($file($api));
    }

    foreach (['= /api/private', '^~ /api/private/'] as $header) {
        expect($parsed['blocks'][$header])
            ->toContain($file($api))
            ->not->toContain($file($siteWide))
            ->not->toContain($file($admin));
    }
});

it('names every current rule on the line that cleans up stale password files', function () {
    // Tripwire: a cleanup that forgets one current rule would delete a live password file and turn
    // every request to the protected area into a 500. This reads the single find/rm line the template
    // uses today; if the cleanup is rewritten into another shape, replace this with a sandbox run.
    $site = securityScriptScenario('several rules');
    $ids = $site->securityRules()->pluck('id');

    $script = renderSecuritySync($site);

    $found = preg_match('/^[ \t]*+(?!#)[^\n]*\b(?:find|rm)\b[^\n]*\$\{?HTPASSWD_DIR\}?[^\n]*(?:-delete|rm )[^\n]*$/m', $script, $cleanup);

    expect($found)->toBe(1, 'No single-line find/rm cleanup of $HTPASSWD_DIR found: the shape changed, update this test.');

    foreach ($ids as $id) {
        expect($cleanup[0])->toMatch("/(?<![0-9]){$id}(?![0-9])/");
    }
});

it('tests the nginx configuration before it reloads nginx', function (string $scenario) {
    $script = renderSecuritySync(securityScriptScenario($scenario));

    $test = securityScriptCommandOffset($script, 'nginx -t');
    $reload = securityScriptCommandOffset($script, 'service nginx reload');

    expect($test)->not->toBeFalse()
        ->and($reload)->not->toBeFalse()
        ->and($test)->toBeLessThan($reload);
})->with(securityScriptScenarios());

it('fails the script before the reload when nginx rejects the configuration', function (string $scenario) {
    $script = renderSecuritySync(securityScriptScenario($scenario));

    $test = securityScriptCommandOffset($script, 'nginx -t');
    $reload = securityScriptCommandOffset($script, 'service nginx reload');
    $failureExit = strpos($script, 'exit 1', (int) $test);

    // The guard is really tied to the result of nginx -t ...
    expect($script)->toMatch('/\bif\s+!\s+nginx -t\b|\bnginx -t\s*\|\|/');

    // ... and the failure exit comes before the reload.
    expect($failureExit)->not->toBeFalse()
        ->and($failureExit)->toBeLessThan($reload);
})->with(securityScriptScenarios(withEmpty: false));

it('removes auth.conf and the htpasswd files when the site has no rules left', function () {
    $site = securityScriptSite();

    $script = renderSecuritySync($site);

    expect($script)
        ->toContain('rm -f "$AUTH_CONF"')
        ->not->toMatch('/^[ \t]*+(?!#)[^\n]*\bauth_basic(_user_file)?[ \t]/m')
        ->not->toContain("<< 'AUTHEOF'")
        ->not->toContain('HTPASSWDEOF')
        // The removal only takes effect once nginx has been reloaded.
        ->toContain('service nginx reload');

    // Every sync also carries an `rm -f "$AUTH_CONF"` in its nginx -t failure branch, so the clean-up
    // only counts when it happens before the first nginx -t, in the main flow.
    expect(securityScriptCommandOffset($script, 'rm -f "$AUTH_CONF"'))
        ->toBeLessThan(securityScriptCommandOffset($script, 'nginx -t'));

    // The password files go too: a cleanup that spares nothing.
    expect($script)->toMatch('/^[ \t]*+(?!#)[^\n]*\b(?:find|rm)\b[^\n]*\$\{?HTPASSWD_DIR/m')
        ->not->toMatch('/^[ \t]*+(?!#)[^\n]*\bfind\b[^\n]*HTPASSWD_DIR[^\n]*! -name/m');
});

it('denies dotfiles below a protected path, because ^~ switches the site-wide deny off', function (string $state, string $path) {
    // The site's own `location ~ /\.(?!well-known).* { deny all; }` is a regex location, and a ^~ prefix
    // location stops regex matching: without a deny of its own, /p/.git/config is served to anyone who
    // has the password.
    $site = securityScriptSite($state);
    securityScriptRule($site, ['path' => $path]);

    $parsed = securityNginxParse(securityAuthConf(renderSecuritySync($site)));
    $nested = securityNginxParse($parsed['blocks']["^~ {$path}/"])['blocks'];

    expect(array_keys($nested))->toContain('~ /\.(?!well-known).*')
        ->and($nested['~ /\.(?!well-known).*'])->toContain('deny all;')
        ->and($nested['~ /\.(?!well-known).*'])->not->toContain('allow');

    // The ACME challenge directory has to stay reachable, hence the lookahead; and the exact location
    // cannot carry a nested one at all.
    expect(securityNginxParse($parsed['blocks']["= {$path}"])['blocks'])->toBeEmpty();
})->with([
    'a php site' => ['laravel', '/admin'],
    'a static site' => ['static', '/private'],
]);

it('puts the dotfile deny in front of the php handler: the first matching regex location wins', function () {
    $site = securityScriptSite('laravel');
    securityScriptRule($site, ['path' => '/admin']);

    $prefix = securityNginxParse(securityAuthConf(renderSecuritySync($site)))['blocks']['^~ /admin/'];

    // Nested regex locations are tried in the order they are written and the first match is used. A request for
    // /admin/.hidden/x.php matches both, so it only runs into the deny if the deny is written first.
    // Measured with nginx 1.25.4 (GET /admin/.hidden/x.php with valid credentials): deny first -> 403, php handler first -> 200 from the handler.
    expect(array_keys(securityNginxParse($prefix)['blocks']))->toBe(['~ /\.(?!well-known).*', '~ \.php$']);

    expect(strpos($prefix, 'location ~ /\.(?!well-known)'))->toBeInt()
        ->toBeLessThan(strpos($prefix, 'location ~ \.php$'));
});

it('serves a protected .php path through fastcgi directly in the exact location', function (string $state, string $path) {
    // A path that ends in .php is one file, so there is no subtree and no "^~ /p/" sibling. The exact location
    // would serve the file as plain text without a handler, and nginx does not allow a location inside it, so
    // the fastcgi directives sit directly in the block.
    $site = securityScriptSite($state);
    $rule = securityScriptRule($site, ['name' => 'Login', 'path' => $path]);

    $parsed = securityNginxParse(securityAuthConf(renderSecuritySync($site)));

    expect(array_keys($parsed['blocks']))->toBe(["= {$path}"]);

    $block = $parsed['blocks']["= {$path}"];

    expect(securityNginxParse($block)['blocks'])->toBeEmpty()
        ->and($block)
        ->toContain('auth_basic "Login";')
        ->toContain("auth_basic_user_file /etc/nginx/netipar-conf/example.com/htpasswd/{$rule->id};")
        ->toContain('fastcgi_pass unix:'.$site->getPhpSocketPath().';')
        ->toContain('include fastcgi_params;')
        ->toContain('include netipar_fastcgi_defaults;');
})->with([
    'wp-login.php on a laravel site' => ['laravel', '/wp-login.php'],
    'wp-login.php on a wordpress site' => ['wordpress', '/wp-login.php'],
    'a file in a directory' => ['laravel', '/admin/login.php'],
    'xmlrpc.php' => ['wordpress', '/xmlrpc.php'],
]);

it('does not give a static site a php handler, not even for a path that ends in .php', function () {
    // The .php exception is for php sites. A site without php has no socket to point fastcgi_pass at, and
    // `fastcgi_pass unix:;` would fail nginx -t for the whole server.
    $site = securityScriptSite('static');
    securityScriptRule($site, ['path' => '/downloads/report.php']);

    expect(securityAuthConf(renderSecuritySync($site)))->not->toContain('fastcgi');
});

it('stops a php site without a php version before it writes anything', function (string $state) {
    $site = securityScriptSite($state, ['php_version' => null]);
    securityScriptRule($site, ['path' => '/admin']);

    $script = renderSecuritySync($site);

    securityAssertStopsBeforeWriting($script, '/\b(?:echo|printf)\b[^\n]*php/i');

    // The forbidden way out: carrying on without the nested php handler would reopen the raw-source hole.
    expect($script)->not->toMatch('/fastcgi_pass\s+unix:\s*;/');
})->with([
    'a laravel site' => ['laravel'],
    'a wordpress site' => ['wordpress'],
]);

it('does not stop a php site without a php version for a site-wide rule, which never refers to the socket', function (string $state) {
    $site = securityScriptSite($state, ['php_version' => null]);
    securityScriptRule($site, ['name' => 'Staging']);

    $script = renderSecuritySync($site);

    // Whatever exits early would sit in front of the first write; here the only exit is the one that
    // follows a failed nginx -t, after auth.conf has been written.
    expect(securityScriptCommandOffset($script, 'exit 1'))->toBeGreaterThan(strpos($script, "<< 'AUTHEOF'"))
        ->and(securityAuthConf($script))->not->toContain('fastcgi');
})->with([
    'a laravel site' => ['laravel'],
    'a wordpress site' => ['wordpress'],
]);

it('does not stop a php site without a php version when the last rule is taken away', function () {
    $site = securityScriptSite('laravel', ['php_version' => null]);

    $script = renderSecuritySync($site);

    expect(securityScriptCommandOffset($script, 'exit 1'))->toBeGreaterThan(securityScriptCommandOffset($script, 'rm -f "$AUTH_CONF"'));
});

it('never lets a rule name switch authentication off', function (string $name) {
    // nginx compares the realm with the literal "off" when the request arrives, quotes or not, and `auth_basic "off";`
    // switches authentication OFF. Measured with nginx 1.25.4, no credentials: "off" and 'off' answer 200 with the
    // page, exactly like the bare `auth_basic off;`, while "Off", "OFF", " off", "off " and "office" answer 401. So a
    // site-wide rule with such a name would make the whole site public while it reads Installed. The names below
    // reach `off` by themselves or only after the sanitising has dropped what stood in the way.
    $site = securityScriptSite();
    securityScriptRule($site, ['name' => $name]);

    $conf = securityAuthConf(renderSecuritySync($site));

    preg_match_all('/^auth_basic[ \t]+"(.*)";$/m', $conf, $realms);

    expect($realms[1])->toBe(['Restricted']);
})->with([
    'off itself' => ['off'],
    'off once the dollar sign is dropped' => ['o$ff'],
    'dollar signs all around it' => ['$off$'],
    'off once a control character is dropped' => ["of\x01f"],
    'off after a leading control character' => ["\x00off"],
    'off through dollar signs and control characters together' => ["\x1Fo\$f\$f\x7F"],
]);

it('keeps the realm of a name that only looks like off', function (string $name) {
    // The special value is the exact lowercase token (measured: "office", " off" and "off " answer 401), so a name that
    // merely contains or resembles it keeps what the user typed. The case variants "Off" and "OFF" answer 401 as well,
    // which makes renaming them harmless, so they are deliberately not pinned either way.
    $site = securityScriptSite();
    securityScriptRule($site, ['name' => $name]);

    preg_match_all('/^auth_basic[ \t]+"(.*)";$/m', securityAuthConf(renderSecuritySync($site)), $realms);

    expect($realms[1])->toBe([$name]);
})->with([
    'a longer word' => ['office'],
    'with a dash' => ['off-site'],
    'with a leading space' => [' off'],
    'with a trailing space' => ['off '],
]);

it('keeps a path rule from switching authentication off in any of its blocks', function () {
    $site = securityScriptSite();
    securityScriptRule($site, ['name' => 'o$ff', 'path' => '/admin']);

    $conf = securityAuthConf(renderSecuritySync($site));

    preg_match_all('/^[ \t]*auth_basic[ \t]+"(.*)";$/m', $conf, $realms);

    // the exact block, the prefix block and the nested php block
    expect($realms[1])->toBe(['Restricted', 'Restricted', 'Restricted']);
});

it('refuses to write anything when a stored path is not in its canonical form', function (string $path) {
    // Rows written before the path rules existed (the old controller stored whatever was typed) or edited by hand never
    // went through validation, and the first sync of their site renders them. Measured with nginx 1.25.4:
    //  - "//admin", "/./admin", "/admin/.." and the trailing-slash leftover "/admin//" make dead locations: nginx matches the
    //    normalised URI, the rule still reads Installed and /admin/x answers 200 without credentials;
    //  - "/.env" and "/admin/.env" make an EXACT location, which ends the location search before the site's own
    //    `location ~ /\.(?!well-known).* { deny all; }` is tried: GET /.env with the password answers 200 with the
    //    file instead of the 403 the site gives everybody. Not a cosmetic check;
    //  - a line break inside the path makes `nginx -t` fail for the whole server.
    $site = securityScriptSite();
    securityScriptRule($site, ['name' => 'Legacy', 'path' => $path]);

    securityAssertStopsBeforeWriting(renderSecuritySync($site), '/\b(?:echo|printf)\b[^\n]*path/i');
})->with([
    'repeated slashes in front' => ['//admin'],
    'repeated slashes inside' => ['/admin//x'],
    'a dot segment' => ['/./admin'],
    'a parent segment at the end' => ['/admin/..'],
    'a parent segment inside' => ['/admin/../etc'],
    'only a dot' => ['/.'],
    'a dotfile at the root' => ['/.env'],
    'a dotfile below a directory' => ['/admin/.env'],
    'a hidden directory' => ['/.git/config'],
    'a trailing slash, as the old controller stored it' => ['/admin/'],
    'trailing repeated slashes' => ['/admin//'],
    'a line break at the end' => ["/admin\n"],
    'a line break inside' => ["/ad\nmin"],
    'no leading slash' => ['admin'],
    'a space' => ['/ad min'],
    'a semicolon' => ['/admin;'],
    'braces' => ['/admin{}'],
    'a dollar sign' => ['/admin$uri'],
]);

it('also refuses a non-canonical stored path on a static site', function () {
    $site = securityScriptSite('static');
    securityScriptRule($site, ['name' => 'Legacy', 'path' => '/private/']);

    securityAssertStopsBeforeWriting(renderSecuritySync($site), '/\b(?:echo|printf)\b[^\n]*path/i');
});

it('still renders every canonical path shape the validation lets through', function (string $path) {
    $site = securityScriptSite();
    securityScriptRule($site, ['name' => 'Fine', 'path' => $path]);

    $script = renderSecuritySync($site);

    expect(array_keys(securityNginxParse(securityAuthConf($script))['blocks']))->toContain("= {$path}");
})->with([
    'one segment' => ['/admin'],
    'nested segments' => ['/admin/users'],
    'a .php file' => ['/wp-login.php'],
    'dots inside a segment' => ['/v1.2/api'],
    'a tilde and an underscore' => ['/~user/_private'],
    'a leading digit' => ['/2024-report'],
]);

it('writes exactly one htpasswd line per credential, even if a stored hash carries a line break', function () {
    // The hash comes from the controller and is trustworthy, but a corrupted or hand-edited row must not be able to
    // close the heredoc early any more than a name can.
    $site = securityScriptSite();
    $rule = securityScriptRule($site, [], ['anna' => 'pw', 'bela' => 'pw']);

    $rule->credentials->first()->forceFill(['password' => "\$2y\$05\$abc\nHTPASSWDEOF{$rule->id}\ntouch /tmp/injected-by-hash"])->save();

    $script = renderSecuritySync($site->refresh());

    expect(securityHtpasswdLines($script, $rule))->toHaveCount(2)
        ->and(preg_match_all('/^HTPASSWDEOF'.$rule->id.'$/m', $script))->toBe(1)
        ->and($script)->not->toMatch('/^[ \t]*touch /m');
});

it('backs the previous auth.conf up before it writes the new one', function () {
    $script = renderSecuritySync(securityScriptScenario('a site-wide rule'));

    $backup = preg_match('/^[ \t]*+(?!#)[^\n]*\b(?:cp|mv)\b[^\n]*prev/mi', $script, $match, PREG_OFFSET_CAPTURE);

    expect($backup)->toBe(1)
        ->and($match[0][1])->toBeLessThan(strpos($script, "<< 'AUTHEOF'"));
});
