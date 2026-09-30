<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Enums\WildcardBehavior;
use Nip\Domain\Jobs\AddDomainJob;
use Nip\Domain\Jobs\UpdateDomainJob;
use Nip\Domain\Models\Certificate;
use Nip\Domain\Models\DomainRecord;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

/*
 * The domain scripts run as root on servers whose nginx ranges from 1.15 to 1.28, and a config
 * that fails `nginx -t` but stays on disk breaks every later reload of the server and takes all
 * of its sites down at the next restart. Text assertions cannot show that a script leaves the
 * server as it found it, so these tests run the rendered scripts with real bash against a
 * throwaway /etc/nginx. Only two binaries are fake: `nginx` reports a version and fails `-t`
 * the way the real one does for the standalone http2 directive before 1.25.1, and `service`
 * records the reload.
 */

/**
 * With channelDropsAtNginxTest the script's output flows through a reader process, and the fake
 * `nginx -t` kills that reader before it fails: the way `bash script | tee` over ssh loses tee when
 * the channel drops. From then on every write of the script goes to a closed pipe.
 *
 * @param  array<string, string>  $files  Path below /etc/nginx => the contents the server starts with
 * @param  array{nginxTestFailsOnce?: bool, failingSed?: bool, channelDropsAtNginxTest?: bool}  $options
 * @return array{
 *     exitCode: int,
 *     output: string,
 *     files: array<string, string>,
 *     nginxTestCalls: int,
 *     nginxTestSaw: array<int, string>,
 *     backupsWhileTesting: array<int, string>,
 *     leftovers: array<int, string>,
 *     serviceCalls: array<int, string>,
 * }
 */
function domainScriptOnFakeServer(string $script, array $files, string $nginxVersion, array $options = []): array
{
    $root = sys_get_temp_dir().'/domain-script-'.Str::random(12);
    $nginxDir = $root.'/etc/nginx';

    try {
        foreach (['sites-available', 'sites-enabled', 'netipar-conf'] as $directory) {
            File::ensureDirectoryExists($nginxDir.'/'.$directory);
        }

        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($nginxDir.'/'.$path));
            File::put($nginxDir.'/'.$path, $contents);
        }

        foreach (['bin', 'tmp', 'state'] as $directory) {
            File::ensureDirectoryExists($root.'/'.$directory);
        }

        $binaries = [
            'nginx' => <<<'SH'
                #!/bin/bash
                case "$1" in
                    -v)
                        echo "nginx version: nginx/$FAKE_NGINX_VERSION" >&2
                        ;;
                    -t)
                        CALL=$(( $(cat "$FAKE_STATE/nginx-t-calls" 2>/dev/null || echo 0) + 1 ))
                        echo "$CALL" > "$FAKE_STATE/nginx-t-calls"
                        find "$FAKE_NGINX_DIR" -type f | sort > "$FAKE_STATE/nginx-t-$CALL-config.txt"
                        find "$TMPDIR" -type f | sort > "$FAKE_STATE/nginx-t-$CALL-tmp.txt"

                        if [ "$FAKE_NGINX_DROPS_CHANNEL" = 1 ]; then
                            WAITED=0
                            while [ ! -s "$FAKE_STATE/reader.pid" ] && [ "$WAITED" -lt 500 ]; do
                                sleep 0.01
                                WAITED=$(( WAITED + 1 ))
                            done

                            READER=$(cat "$FAKE_STATE/reader.pid")
                            kill -KILL "$READER"

                            # The pipe only counts as closed once the reader is really gone.
                            WAITED=0
                            while kill -0 "$READER" 2>/dev/null && [ "$WAITED" -lt 500 ]; do
                                sleep 0.01
                                WAITED=$(( WAITED + 1 ))
                            done

                            exit 1
                        fi

                        if [ "$FAKE_NGINX_HAS_STANDALONE_HTTP2" != 1 ] && grep -rq '^[[:space:]]*http2 on;' "$FAKE_NGINX_DIR"; then
                            echo 'nginx: [emerg] unknown directive "http2"' >&2
                            exit 1
                        fi

                        if [ "$FAKE_NGINX_FAIL_FIRST_TEST" = 1 ] && [ "$CALL" = 1 ]; then
                            echo 'nginx: configuration file test failed' >&2
                            exit 1
                        fi
                        ;;
                esac
                SH,
            'service' => <<<'SH'
                #!/bin/bash
                echo "service $*" >> "$FAKE_STATE/service.log"
                SH,
            'sed' => <<<'SH'
                #!/bin/bash
                if [ "$FAKE_SED_FAILS" = 1 ]; then
                    for argument in "$@"; do
                        case "$argument" in
                            *'http2 on;'*)
                                echo 'sed: simulated failure' >&2
                                exit 1
                                ;;
                        esac
                    done
                fi

                # The scripts are written for GNU sed, and BSD sed (macOS) wants a suffix after -i.
                if [ "$1" = "-i" ] && [ "$(uname)" = "Darwin" ]; then
                    shift
                    exec "$REAL_SED" -i '' "$@"
                fi

                exec "$REAL_SED" "$@"
                SH,
        ];

        foreach ($binaries as $name => $contents) {
            File::put($root.'/bin/'.$name, $contents."\n");
            chmod($root.'/bin/'.$name, 0755);
        }

        File::put($root.'/script.sh', str_replace('/etc/nginx', $nginxDir, $script));

        $command = ['/bin/bash', $root.'/script.sh'];

        if ($options['channelDropsAtNginxTest'] ?? false) {
            // PHP starts every child with SIGPIPE ignored and bash cannot take that back, while sshd
            // starts the script with the default. perl restores the default: without it the script
            // could never die from SIGPIPE here, and the scenario would pass without the fix.
            $command = ['/bin/bash', '-c', <<<'SH'
                perl -e '$SIG{PIPE} = "DEFAULT"; exec @ARGV' /bin/bash "$0" 2>&1 |
                    sh -c 'echo $$ > "$1"; exec cat > "$2"' sh "$FAKE_STATE/reader.pid" "$FAKE_STATE/channel.txt"
                exit "${PIPESTATUS[0]}"
                SH, $root.'/script.sh'];
        }

        $process = Process::path($root)
            ->env([
                'PATH' => $root.'/bin:'.getenv('PATH'),
                'TMPDIR' => $root.'/tmp',
                'FAKE_STATE' => $root.'/state',
                'FAKE_NGINX_DIR' => $nginxDir,
                'FAKE_NGINX_VERSION' => $nginxVersion,
                'FAKE_NGINX_HAS_STANDALONE_HTTP2' => version_compare($nginxVersion, '1.25.1', '>=') ? '1' : '0',
                'FAKE_NGINX_FAIL_FIRST_TEST' => ($options['nginxTestFailsOnce'] ?? false) ? '1' : '0',
                'FAKE_SED_FAILS' => ($options['failingSed'] ?? false) ? '1' : '0',
                'FAKE_NGINX_DROPS_CHANNEL' => ($options['channelDropsAtNginxTest'] ?? false) ? '1' : '0',
                'REAL_SED' => trim(Process::run(['/bin/sh', '-c', 'command -v sed'])->output()),
            ])
            ->timeout(60)
            ->run($command);

        $listing = function (string $path, ?string $relativeTo = null) use ($root): array {
            $file = $root.'/state/'.$path;

            if (! File::exists($file)) {
                return [];
            }

            return collect(explode("\n", trim(File::get($file))))
                ->filter()
                ->map(fn (string $line): string => $relativeTo === null ? $line : Str::after($line, $relativeTo.'/'))
                ->values()
                ->all();
        };

        return [
            'exitCode' => $process->exitCode(),
            'output' => $process->output().$process->errorOutput().(File::exists($root.'/state/channel.txt') ? File::get($root.'/state/channel.txt') : ''),
            'files' => collect(File::allFiles($nginxDir, true))
                ->reject(fn (SplFileInfo $file): bool => $file->isLink())
                ->mapWithKeys(fn (SplFileInfo $file): array => [
                    Str::after($file->getPathname(), $nginxDir.'/') => File::get($file->getPathname()),
                ])
                ->sortKeys()
                ->all(),
            'nginxTestCalls' => (int) (File::exists($root.'/state/nginx-t-calls') ? File::get($root.'/state/nginx-t-calls') : 0),
            'nginxTestSaw' => $listing('nginx-t-1-config.txt', $nginxDir),
            'backupsWhileTesting' => $listing('nginx-t-1-tmp.txt'),
            'leftovers' => array_values(array_diff(scandir($root.'/tmp'), ['.', '..'])),
            'serviceCalls' => $listing('service.log'),
        ];
    } finally {
        File::deleteDirectory($root);
    }
}

/**
 * Whether the channel-drop scenario starts the script with the default SIGPIPE disposition, so that
 * a write to a closed pipe kills it. If it does not (no perl to undo what PHP does), the scenario
 * would pass for a script that never handles the signal, and proves nothing.
 */
function domainScriptDiesFromSigpipe(): bool
{
    $result = domainScriptOnFakeServer('kill -PIPE $$', [], '1.28.1', ['channelDropsAtNginxTest' => true]);

    return $result['exitCode'] === 141;
}

function domainScriptFor(DomainRecord $record, string $job = UpdateDomainJob::class): string
{
    $job = new $job($record);

    return (new ReflectionClass($job))->getMethod('generateScript')->invoke($job);
}

/**
 * @param  array<int, string>  $domains
 */
function domainScriptCertificate(Site $site, array $domains): Certificate
{
    return Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => $domains,
        'active' => true,
    ]);
}

/**
 * What a site has on disk before the fix: the main config, the legacy site-level www redirect
 * that provisioning wrote, and the ssl redirect that enabling SSL added.
 *
 * The main config is one the enable-ssl script already switched to 443, in the syntax it writes
 * for that nginx, unless $sslFirst is false. A redirect-type domain is the exception: its config
 * starts with a port 80 block whatever its SSL state, so the script never sees it as SSL first.
 *
 * @return array<string, string>
 */
function domainScriptServerFiles(string $domain = 'example.com', string $nginxVersion = '1.28.1', bool $sslFirst = true): array
{
    $main = match (true) {
        ! $sslFirst => "server {\n    listen 80;\n    listen [::]:80;\n    server_name {$domain};\n}\n",
        version_compare($nginxVersion, '1.26.0', '>=') => "server {\n    listen 443 ssl;\n    http2 on;\n    listen [::]:443 ssl;\n    server_name {$domain} www.{$domain};\n    ssl_certificate /etc/nginx/ssl/{$domain}/fullchain.crt;\n    ssl_certificate_key /etc/nginx/ssl/{$domain}/private.key;\n}\n",
        default => "server {\n    listen 443 ssl http2;\n    listen [::]:443 ssl http2;\n    server_name {$domain} www.{$domain};\n    ssl_certificate /etc/nginx/ssl/{$domain}/fullchain.crt;\n    ssl_certificate_key /etc/nginx/ssl/{$domain}/private.key;\n}\n",
    };

    return [
        "sites-available/{$domain}" => $main,
        'netipar-conf/example.com/site.conf' => "# site common configuration\n",
        'netipar-conf/example.com/before/redirect.conf' => "# legacy site-level www redirect\nserver {\n    listen 80;\n    server_name www.example.com;\n    return 301 \$scheme://example.com\$request_uri;\n}\n",
        "netipar-conf/example.com/{$domain}/before/ssl_redirect.conf" => "# HTTP to HTTPS redirect for {$domain}\nserver {\n    listen 80;\n    server_name {$domain};\n    return 301 https://\$host\$request_uri;\n}\n",
    ];
}

/**
 * A primary domain with a certificate covering its www name: the application block on 443 and the
 * www redirect block on 443.
 *
 * @return array{record: DomainRecord, blocks: int, sslFirst: bool}
 */
function domainScriptWwwRedirectShape(): array
{
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);
    domainScriptCertificate($site, ['example.com', 'www.example.com']);

    return [
        'record' => DomainRecord::factory()->for($site)->primary()->create([
            'name' => 'example.com',
            'www_redirect_type' => WwwRedirectType::FromWww,
        ]),
        'blocks' => 2,
        'sslFirst' => true,
    ];
}

/**
 * A wildcard primary domain whose unconfigured subdomains are redirected: the application block
 * on 443 and the wildcard catch-all block on 443, in one file.
 *
 * @return array{record: DomainRecord, blocks: int, sslFirst: bool}
 */
function domainScriptWildcardShape(): array
{
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);
    domainScriptCertificate($site, ['example.com', '*.example.com']);

    return [
        'record' => DomainRecord::factory()->for($site)->primary()->create([
            'name' => 'example.com',
            'allow_wildcard' => true,
            'wildcard_behavior' => WildcardBehavior::Redirect,
        ]),
        'blocks' => 2,
        'sslFirst' => true,
    ];
}

/**
 * A redirect-type domain: its own template, with one block on 443.
 *
 * @return array{record: DomainRecord, blocks: int, sslFirst: bool}
 */
function domainScriptRedirectTypeShape(): array
{
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);
    domainScriptCertificate($site, ['example.com', '*.example.com']);

    return [
        'record' => DomainRecord::factory()->for($site)->create([
            'name' => 'go.example.com',
            'type' => DomainRecordType::Redirect,
            'redirect_target' => 'target.example.org',
        ]),
        'blocks' => 1,
        'sslFirst' => false,
    ];
}

/**
 * @param  array<string, string>  $files
 */
function domainScriptExpectHttp2Syntax(array $files, bool $standalone, int $blocks): void
{
    $config = implode("\n", $files);

    if ($standalone) {
        expect(substr_count($config, 'http2 on;'))->toBe($blocks)
            ->and(substr_count($config, 'listen 443 ssl;'))->toBe($blocks)
            ->and($config)->not->toContain('ssl http2;');

        return;
    }

    expect($config)->not->toContain('http2 on;')
        ->and(substr_count($config, 'listen 443 ssl http2;'))->toBe($blocks)
        ->and(substr_count($config, 'listen [::]:443 ssl http2;'))->toBe($blocks);
}

// ---------------------------------------------------------------------------
// http2 syntax: the server, not the template, knows which one it can parse
// ---------------------------------------------------------------------------

it('writes the http2 syntax the installed nginx understands', function (string $shape, string $version, bool $standalone) {
    ['record' => $record, 'blocks' => $blocks, 'sslFirst' => $sslFirst] = $shape();

    $result = domainScriptOnFakeServer(
        domainScriptFor($record),
        domainScriptServerFiles($record->name, $version, $sslFirst),
        $version,
    );

    // The standalone `http2 on;` is unknown to nginx before 1.25.1: the fake `nginx -t` rejects it
    // there, so the script only exits 0 when it wrote the listen-parameter form instead. The
    // threshold is the one certificate/enable-ssl uses, so 1.25.x gets the deprecated but valid form.
    expect($result['exitCode'])->toBe(0, $result['output']);

    domainScriptExpectHttp2Syntax($result['files'], $standalone, $blocks);
})->with([
    'www redirect and application block, nginx 1.15.8 (Dartsticket)' => ['domainScriptWwwRedirectShape', '1.15.8', false],
    'www redirect and application block, nginx 1.20.1 (RHD)' => ['domainScriptWwwRedirectShape', '1.20.1', false],
    'www redirect and application block, nginx 1.25.3' => ['domainScriptWwwRedirectShape', '1.25.3', false],
    'www redirect and application block, nginx 1.26.0' => ['domainScriptWwwRedirectShape', '1.26.0', true],
    'www redirect and application block, nginx 1.28.1 (NETipar)' => ['domainScriptWwwRedirectShape', '1.28.1', true],
    'wildcard redirect block, nginx 1.20.1' => ['domainScriptWildcardShape', '1.20.1', false],
    'wildcard redirect block, nginx 1.28.1' => ['domainScriptWildcardShape', '1.28.1', true],
    'redirect-type block, nginx 1.20.1' => ['domainScriptRedirectTypeShape', '1.20.1', false],
    'redirect-type block, nginx 1.28.1' => ['domainScriptRedirectTypeShape', '1.28.1', true],
]);

it('adds a domain with the http2 syntax the installed nginx understands', function (string $version, bool $standalone) {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);
    domainScriptCertificate($site, ['example.com', 'www.example.com']);
    $record = DomainRecord::factory()->for($site)->primary()->create([
        'name' => 'example.com',
        'status' => DomainRecordStatus::Creating,
        'www_redirect_type' => WwwRedirectType::FromWww,
    ]);

    // The add script has no rollback at all, so a config nginx cannot parse would stay on disk.
    $result = domainScriptOnFakeServer(domainScriptFor($record, AddDomainJob::class), [], $version);

    expect($result['exitCode'])->toBe(0, $result['output']);

    domainScriptExpectHttp2Syntax($result['files'], $standalone, 2);
})->with([
    'nginx 1.20.1 (RHD)' => ['1.20.1', false],
    'nginx 1.28.1 (NETipar)' => ['1.28.1', true],
]);

// ---------------------------------------------------------------------------
// Rollback: a failed update leaves the server exactly as it found it
// ---------------------------------------------------------------------------

it('puts every file back when the nginx test fails', function (WwwRedirectType $type, ?string $existingRedirect) {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);
    domainScriptCertificate($site, ['example.com', 'www.example.com']);
    $record = DomainRecord::factory()->for($site)->primary()->create([
        'name' => 'example.com',
        'www_redirect_type' => $type,
    ]);

    $files = domainScriptServerFiles('example.com', '1.28.1');

    if ($existingRedirect !== null) {
        $files['netipar-conf/example.com/example.com/before/redirect.conf'] = $existingRedirect;
    }

    ksort($files);

    $result = domainScriptOnFakeServer(domainScriptFor($record), $files, '1.28.1', ['nginxTestFailsOnce' => true]);

    // Guards the scenario: by the time the test failed the script had already changed the include
    // files, and the site-level one was gone. Restoring only the main config leaves all of that.
    expect($result['nginxTestSaw'])->not->toBe(array_keys($files))
        ->and($result['exitCode'])->toBe(1)
        // Byte for byte: the main config, the site-level include the update retired, the ssl
        // redirect, and the domain-level redirect (removed again when it did not exist before,
        // restored when the update overwrote or deleted it).
        ->and($result['files'])->toBe($files)
        ->and($result['nginxTestCalls'])->toBe(2)
        ->and($result['leftovers'])->toBe([]);
})->with([
    'a new www redirect was written' => [WwwRedirectType::FromWww, null],
    'an existing www redirect was overwritten' => [WwwRedirectType::FromWww, "# hand-tuned www redirect\n"],
    'an existing www redirect was deleted' => [WwwRedirectType::None, "# hand-tuned www redirect\n"],
]);

it('also puts every file back when the script fails before the nginx test', function () {
    ['record' => $record] = domainScriptWwwRedirectShape();
    $files = domainScriptServerFiles('example.com', '1.20.1');
    ksort($files);

    // On nginx 1.20.1 the script rewrites the http2 syntax with sed, and here that sed fails.
    $result = domainScriptOnFakeServer(domainScriptFor($record), $files, '1.20.1', ['failingSed' => true]);

    expect($result['exitCode'])->not->toBe(0)
        ->and($result['nginxTestCalls'])->toBe(0)
        ->and($result['files'])->toBe($files)
        ->and($result['leftovers'])->toBe([]);
});

it('still puts every file back when the ssh channel is gone by the time the nginx test fails', function () {
    ['record' => $record] = domainScriptWwwRedirectShape();
    $files = domainScriptServerFiles('example.com', '1.28.1');
    ksort($files);

    $result = domainScriptOnFakeServer(
        domainScriptFor($record),
        $files,
        '1.28.1',
        ['channelDropsAtNginxTest' => true],
    );

    // Guards the scenario: the reader saw the script from its first line and nothing after the drop,
    // so the failure was reported into a closed pipe, and by then the script had changed the files.
    expect($result['output'])->toContain('Updating domain example.com')
        ->and($result['output'])->not->toContain('restoring backup')
        ->and($result['nginxTestSaw'])->not->toBe(array_keys($files));

    // The wrapper pipes the script through tee, so a lost channel makes its next write raise SIGPIPE.
    // Ignored, that is an ordinary failed write which `set -e` exits on (status 1). Otherwise bash dies
    // from the signal (128 + 13), and on some versions the write inside finish() kills the restore too.
    expect($result['exitCode'] > 0 && $result['exitCode'] < 128)
        ->toBeTrue("Expected an ordinary failure, the script exited with {$result['exitCode']} (128 + n is a death by signal n, 13 is SIGPIPE)");

    // finish() used to echo before it restored, and with the channel gone that echo must not be able to
    // fail the handler either. Otherwise the new redirect.conf stays on disk, and on nginx before 1.25.1
    // (RHD, Dartsticket) every later reload of the server fails on it.
    expect(array_values(array_diff(array_keys($result['files']), array_keys($files))))->toBe([])
        ->and($result['files'])->toBe($files)
        ->and($result['leftovers'])->toBe([]);
})->skip(fn () => ! domainScriptDiesFromSigpipe(), 'needs perl to give bash back the default SIGPIPE that PHP strips');

it('leaves no backup in the config tree and tests the files it is about to reload', function () {
    ['record' => $record] = domainScriptWwwRedirectShape();

    $result = domainScriptOnFakeServer(domainScriptFor($record), domainScriptServerFiles(), '1.28.1');

    expect($result['exitCode'])->toBe(0, $result['output'])
        // nginx includes every file in a before/ directory, so a backup kept there would be loaded
        // as configuration. At the moment of the test the tree holds the new redirect and nothing
        // else new: no backup, and the legacy site-level file is already gone.
        ->and($result['nginxTestSaw'])->toBe([
            'netipar-conf/example.com/example.com/before/redirect.conf',
            'netipar-conf/example.com/example.com/before/ssl_redirect.conf',
            'netipar-conf/example.com/site.conf',
            'sites-available/example.com',
        ])
        // The copies exist while the test runs, outside the tree, and are gone afterwards.
        ->and($result['backupsWhileTesting'])->not->toBe([])
        ->and($result['leftovers'])->toBe([])
        ->and($result['serviceCalls'])->toBe(['service nginx reload'])
        ->and(array_keys($result['files']))->not->toContain('netipar-conf/example.com/before/redirect.conf');
});

it('never touches the site-level redirect when an alias is updated', function (bool $nginxTestFails) {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);
    domainScriptCertificate($site, ['example.com', '*.example.com']);
    DomainRecord::factory()->for($site)->primary()->create(['name' => 'example.com']);
    $alias = DomainRecord::factory()->for($site)->alias()->create(['name' => 'shop.example.com']);

    $files = domainScriptServerFiles('shop.example.com', '1.28.1');
    $legacy = $files['netipar-conf/example.com/before/redirect.conf'];

    $result = domainScriptOnFakeServer(
        domainScriptFor($alias),
        $files,
        '1.28.1',
        ['nginxTestFailsOnce' => $nginxTestFails],
    );

    expect($result['exitCode'])->toBe($nginxTestFails ? 1 : 0)
        ->and($result['files']['netipar-conf/example.com/before/redirect.conf'])->toBe($legacy);
})->with([
    'the update succeeds' => [false],
    'the nginx test fails' => [true],
]);
