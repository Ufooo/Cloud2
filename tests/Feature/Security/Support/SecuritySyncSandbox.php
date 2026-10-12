<?php

namespace Tests\Feature\Security\Support;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

/**
 * A throw-away directory in which the RENDERED sync script is run for real, without any server.
 *
 * Why this exists: a render test can only look at the text of the script, and "the old auth.conf survives a
 * failed nginx -t" or "nothing is written when the PHP socket is missing" are statements about files on disk
 * after the script ran. No job is dispatched, no SSH connection is opened, nothing talks to the network.
 *
 * What it is NOT: a security boundary. It protects against MISTAKES in a script we render ourselves (an unset
 * variable, a wrongly built path), not against a hostile script. The pieces, and what each one really covers:
 *
 *  1. Before the run, every absolute path the script names (outside heredoc bodies and comments) has to
 *     resolve, with "." and ".." collapsed, to the sandbox root or something below it ("/", "/*", "<root>/..",
 *     "<root>-other/x" are all refused), and eval, exec and source are refused. Paths that are only built at
 *     run time ("$DIR/x") are not visible to this check, that is what 3 is for.
 *  2. The script runs under `bash -u`: an unset variable stops it instead of silently becoming an empty
 *     path ("$DIR"/* turning into /*).
 *  3. rm, mv, cp, find, chmod, mkdir, touch, ln and cd are shell functions that look at every argument first;
 *     one that resolves outside the sandbox (lexically, symlinks are not followed) kills the shell and the
 *     run ends in a SandboxViolation. Redirections ("cat > $X") cannot be checked this way, and the
 *     builtins other than cd are not wrapped.
 *  4. PATH holds links to a short list of tools only, so any other command is "command not found". nginx,
 *     service and chown are shell functions that just log.
 *  5. The nginx stub logs what the include glob (server/*) would pick up on every call, which is how a backup
 *     that lands in server/ (and would make real nginx fail with a duplicate directive) shows up.
 *  6. The directory is deleted after the test.
 *
 * Links and functions instead of wrapper scripts on purpose: macOS scans every freshly written executable
 * on its first run (about 200 ms each), which made a single sync take seconds.
 */
final class SecuritySyncSandbox
{
    private const REAL_CONF_ROOT = '/etc/nginx/netipar-conf';

    /** Tools on the PATH. The mutating ones are wrapped by guard functions as well, see installStubs(). */
    private const TOOLS = ['cat', 'chmod', 'cp', 'mv', 'rm', 'mkdir', 'find', 'ls', 'tr', 'grep', 'sort', 'uniq', 'dirname', 'basename', 'touch', 'ln', 'head', 'tail', 'wc', 'cmp', 'diff', 'date'];

    /** @var list<string> */
    private const REQUIRED_TOOLS = ['cat', 'chmod', 'cp', 'mv', 'rm', 'mkdir', 'find', 'ls', 'tr', 'grep'];

    /** @var list<string> */
    private static array $roots = [];

    public readonly string $root;

    public readonly string $confDir;

    private function __construct(string $root, public readonly string $domain, private readonly string $bash)
    {
        $this->root = $root;
        $this->confDir = $root.'/conf/'.$domain;
    }

    public static function isAvailable(): bool
    {
        if (PHP_OS_FAMILY === 'Windows' || self::findExecutable('bash') === null) {
            return false;
        }

        foreach (self::REQUIRED_TOOLS as $tool) {
            if (self::findExecutable($tool) === null) {
                return false;
            }
        }

        return true;
    }

    public static function create(string $domain = 'example.com'): self
    {
        $bash = self::findExecutable('bash');
        Assert::assertNotNull($bash, 'bash is required for the sandbox');

        $root = sys_get_temp_dir().'/security-sync-'.bin2hex(random_bytes(6));
        mkdir($root.'/bin', 0700, true);
        mkdir($root.'/tmp', 0700);

        $sandbox = new self((string) realpath($root), $domain, $bash);
        self::$roots[] = $sandbox->root;
        $sandbox->installTools();
        $sandbox->installStubs();

        return $sandbox;
    }

    public static function cleanup(): void
    {
        foreach (self::$roots as $root) {
            if (! is_dir($root) || ! str_contains($root, 'security-sync-')) {
                continue;
            }

            // The tool links point at the real binaries: unlink them first so nothing can ever follow them.
            foreach (glob($root.'/bin/*') ?: [] as $link) {
                if (is_link($link)) {
                    unlink($link);
                }
            }

            File::deleteDirectory($root);
        }

        self::$roots = [];
    }

    /**
     * Create a file below the site's conf dir, e.g. put('server/auth.conf', ...).
     */
    public function put(string $relativePath, string $content): void
    {
        $path = $this->confDir.'/'.$relativePath;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
    }

    public function exists(string $relativePath): bool
    {
        return file_exists($this->confDir.'/'.$relativePath);
    }

    public function read(string $relativePath): ?string
    {
        $path = $this->confDir.'/'.$relativePath;

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function mode(string $relativePath): string
    {
        return substr(sprintf('%o', fileperms($this->confDir.'/'.$relativePath)), -3);
    }

    /**
     * Everything below the site's conf dir, files and directories (the latter with a trailing slash).
     *
     * @return list<string>
     */
    public function files(): array
    {
        if (! is_dir($this->confDir)) {
            return [];
        }

        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->confDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $path => $info) {
            $found[] = substr((string) $path, strlen($this->confDir) + 1).($info->isDir() ? '/' : '');
        }

        sort($found);

        return $found;
    }

    /**
     * Run the rendered script. Extra env vars steer the stubs: NGINX_BAD_MARKER (nginx -t fails while any file in
     * server/ contains it), NGINX_ALWAYS_FAIL=1 (it always fails), NGINX_INTERRUPT=TERM|HUP|PIPE|KILL (the script
     * gets that signal ONCE, at its first nginx call, like a dropped SSH connection; NGINX_KILL_PARENT=1 is KILL),
     * CAT_INTERRUPT=TERM|HUP|PIPE|KILL (the write of auth.conf is cut off half way and the script gets that
     * signal). SANDBOX_ROOT is always available to the script.
     *
     * The harness runs the script under `bash -u`. Pass $nounset = false to see what the script does on its own,
     * the way it runs on a server where nobody adds that flag.
     *
     * @param  array<string, string>  $env
     * @return array{exit: int, output: string, error: string}
     *
     * @throws SandboxViolation when the script is refused up front or reaches outside the sandbox while it runs
     */
    public function run(string $script, array $env = [], bool $nounset = true): array
    {
        $rewritten = $this->localize($script);
        $this->assertContained($rewritten);

        file_put_contents($this->root.'/run.sh', $rewritten);

        $process = new Process(array_values(array_filter([$this->bash, $nounset ? '-u' : null, $this->root.'/run.sh'])), $this->root, $this->environment($env), null, 30);

        try {
            $process->run();
        } catch (ProcessSignaledException) {
            // Killed on purpose (a stub, or the guard functions): the exit code says so below.
        }

        $blocked = array_values(array_filter($this->calls(), fn (string $call) => str_starts_with($call, 'BLOCKED:')));

        if ($blocked !== []) {
            throw new SandboxViolation('The script reached outside the sandbox while it ran and was stopped: '.$blocked[0]);
        }

        return [
            'exit' => $process->hasBeenSignaled() ? 128 + $process->getTermSignal() : (int) $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }

    /**
     * Run the script and cut its output connection once a line containing $afterLine has been read, the way a dropped
     * SSH session looks to the script: nobody reads any more, so every later write fails with "Broken pipe". The write
     * of auth.conf waits (CAT_WAIT_FOR) until the connection is cut, so the cut always falls before the config is moved
     * into place and the next line of output, which makes the run deterministic.
     *
     * @param  array<string, string>  $env
     * @return int the exit code of the script
     */
    public function runWithOutputDroppedAfter(string $script, string $afterLine, array $env = []): int
    {
        $rewritten = $this->localize($script);
        $this->assertContained($rewritten);

        file_put_contents($this->root.'/run.sh', $rewritten);

        $go = $this->root.'/tmp/output-dropped';
        $environment = array_filter($this->environment($env + ['CAT_WAIT_FOR' => $go]), fn ($value) => $value !== false);

        $process = proc_open([$this->bash, '-u', $this->root.'/run.sh'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root, $environment);

        Assert::assertIsResource($process);

        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 20);

        while (($line = fgets($pipes[1])) !== false) {
            if (str_contains($line, $afterLine)) {
                break;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        touch($go);

        return proc_close($process);
    }

    /**
     * Everything the stubs logged, in order: "nginx -t [server/: auth.conf]", "service nginx reload", "chown ...".
     *
     * @return list<string>
     */
    public function calls(): array
    {
        $log = $this->root.'/calls.log';

        return is_file($log) ? array_values(array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    }

    /**
     * For every `nginx -t` the script made: the files that the include glob server/* would have read at that moment.
     *
     * @return list<list<string>>
     */
    public function nginxTests(): array
    {
        $tests = [];

        foreach ($this->calls() as $call) {
            if (preg_match('/^nginx -t \[server\/: ?(.*)\]$/', $call, $match) === 1) {
                $tests[] = $match[1] === '' ? [] : explode(' ', $match[1]);
            }
        }

        return $tests;
    }

    /**
     * Text as the sandbox sees it: the script's own path rewrite applied to expected content.
     */
    public function localize(string $text): string
    {
        return str_replace(self::REAL_CONF_ROOT, $this->root.'/conf', $text);
    }

    public function reloaded(): bool
    {
        return in_array('service nginx reload', $this->calls(), true);
    }

    /**
     * Refuse, before anything runs, a script that names a path outside the sandbox. Heredoc bodies (they are
     * data) and comment lines are skipped. Paths that are built at run time are the guard functions' business.
     */
    private function assertContained(string $script): void
    {
        if (str_contains($script, '/etc/')) {
            throw new SandboxViolation('The script still points at /etc/: refusing to run it.');
        }

        $code = (string) preg_replace("/<<[ \t]*'(\\w+)'\\R.*?\\R\\1(?:\\R|\$)/s", "\n", $script);
        $code = (string) preg_replace('/^[ \t]*#.*$/m', '', $code);

        // A slash starts an absolute path unless it continues a word, a variable, a glob or a relative path.
        // After a quote it counts only when that quote OPENS a string: in "$DIR"/* the slash continues the
        // variable, in "/etc" or ""/etc it starts the word.
        preg_match_all('#(?:(?<![\w$})\].*~/\-"\'])|(?<=(?<![\w$})\]])["\']))/[^\s"\'`;|&()<>]*#', $code, $matches);

        foreach ($matches[0] as $token) {
            if (! $this->isInside($token)) {
                throw new SandboxViolation("The script names {$token}, which is outside the sandbox: refusing to run it.");
            }
        }

        if (preg_match('/(?<![\w$.\-])(eval|exec|source|builtin|enable)(?=\s)/', $code, $word) === 1) {
            throw new SandboxViolation("The script uses '{$word[1]}', which could get around the sandbox: refusing to run it.");
        }
    }

    /**
     * Lexical check: the path, with "." and ".." collapsed, is the root itself or below it (a lookalike such as
     * "<root>-other" is not). /dev/null is the one exception.
     */
    private function isInside(string $path): bool
    {
        $normalized = self::normalize($path);

        return $normalized === '/dev/null' || $normalized === $this->root || str_starts_with($normalized, $this->root.'/');
    }

    private static function normalize(string $path): string
    {
        $kept = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($kept);

                continue;
            }

            $kept[] = $part;
        }

        return '/'.implode('/', $kept);
    }

    /**
     * @param  array<string, string>  $env
     * @return array<string, string|false> false removes a variable that the parent process has
     */
    private function environment(array $env): array
    {
        return array_merge([
            'PATH' => $this->root.'/bin',
            'HOME' => $this->root,
            'TMPDIR' => $this->root.'/tmp',
            'LC_ALL' => 'C',
            'BASH_ENV' => $this->root.'/stubs.sh',
            'ENV' => false,
            'CDPATH' => false,
            'GLOBIGNORE' => false,
            'SANDBOX_ROOT' => $this->root,
            'SANDBOX_LOG' => $this->root.'/calls.log',
            'SANDBOX_CONF_DIR' => $this->confDir,
        ], $env);
    }

    private function installTools(): void
    {
        foreach (self::TOOLS as $tool) {
            $real = self::findExecutable($tool);

            if ($real !== null) {
                symlink($real, $this->root.'/bin/'.$tool);
            }
        }
    }

    private function installStubs(): void
    {
        $stubs = <<<'SH'
# ---- guards: a mutating command whose argument resolves outside the sandbox kills the shell -------------
__sandbox_norm() {
    # Sets SANDBOX_NORMALIZED instead of printing: no command substitution, because once the script's stdout is a
    # broken pipe, bash 3.2 flushes the text of the failed echo into the next substitution it runs.
    local path="$1" part out="" oldifs="$IFS" restore_glob=""
    case "$path" in /*) ;; *) path="$PWD/$path" ;; esac
    case "$-" in *f*) ;; *) restore_glob=1; set -f ;; esac
    IFS=/
    for part in $path; do
        case "$part" in
            ''|.) ;;
            ..) out="${out%/*}" ;;
            *) out="$out/$part" ;;
        esac
    done
    IFS="$oldifs"
    if [ -n "$restore_glob" ]; then set +f; fi
    SANDBOX_NORMALIZED="${out:-/}"
}
__sandbox_guard() {
    local tool="$1" arg
    shift
    for arg in "$@"; do
        case "$arg" in -*) continue ;; esac
        __sandbox_norm "$arg"
        case "$SANDBOX_NORMALIZED" in
            "$SANDBOX_ROOT"|"$SANDBOX_ROOT"/*|/dev/null) ;;
            *)
                echo "BLOCKED: $tool $arg (resolves to $SANDBOX_NORMALIZED)" >> "$SANDBOX_LOG"
                echo "sandbox: refusing '$tool $arg', it resolves outside the sandbox" >&2
                kill -9 $$
                ;;
        esac
    done
}
rm() { __sandbox_guard rm "$@"; command rm "$@"; }
mv() { __sandbox_guard mv "$@"; command mv "$@"; }
cp() { __sandbox_guard cp "$@"; command cp "$@"; }
find() { __sandbox_guard find "$@"; command find "$@"; }
chmod() { __sandbox_guard chmod "$@"; command chmod "$@"; }
mkdir() { __sandbox_guard mkdir "$@"; command mkdir "$@"; }
touch() { __sandbox_guard touch "$@"; command touch "$@"; }
ln() { __sandbox_guard ln "$@"; command ln "$@"; }
cd() { __sandbox_guard cd "$@"; builtin cd "$@"; }

# ---- stand-ins for what is not there: they only log -----------------------------------------------------
nginx() {
    local server_dir="$SANDBOX_CONF_DIR/server" listing=""
    if [ -d "$server_dir" ]; then listing="$(ls -1 "$server_dir" | tr '\n' ' ')"; listing="${listing% }"; fi
    echo "nginx $* [server/: $listing]" >> "$SANDBOX_LOG"
    if [ "${NGINX_KILL_PARENT:-}" = "1" ]; then kill -9 $$; fi
    if [ -n "${NGINX_INTERRUPT:-}" ] && [ ! -e "$SANDBOX_ROOT/tmp/nginx-interrupted" ]; then
        : > "$SANDBOX_ROOT/tmp/nginx-interrupted"
        kill -s "${NGINX_INTERRUPT}" $$
    fi
    if [ "${NGINX_ALWAYS_FAIL:-}" = "1" ]; then
        echo "nginx: [emerg] simulated failure that has nothing to do with this site" >&2
        return 1
    fi
    if [ -n "${NGINX_BAD_MARKER:-}" ] && [ -d "$server_dir" ] && grep -q -- "$NGINX_BAD_MARKER" "$server_dir"/* 2>/dev/null; then
        echo "nginx: [emerg] simulated failure caused by the configuration in server/" >&2
        return 1
    fi
    return 0
}
service() { echo "service $*" >> "$SANDBOX_LOG"; }
chown() { echo "chown $*" >> "$SANDBOX_LOG"; }

# ---- an interrupted write: the script is cut off half way through writing auth.conf ---------------------
cat() {
    if [ -z "${CAT_INTERRUPT:-}" ] && [ -z "${CAT_WAIT_FOR:-}" ]; then command cat "$@"; return; fi
    local input="$SANDBOX_ROOT/tmp/cat-stdin.$$"
    command cat > "$input"
    if grep -q -- '^# Netipar Cloud - HTTP Basic Auth for' "$input"; then
        if [ -n "${CAT_WAIT_FOR:-}" ]; then
            local spins=0
            while [ ! -e "$CAT_WAIT_FOR" ] && [ "$spins" -lt 400000 ]; do spins=$((spins + 1)); done
        fi
        if [ -n "${CAT_INTERRUPT:-}" ]; then
            echo "interrupted ${CAT_INTERRUPT}" >> "$SANDBOX_LOG"
            head -c 40 "$input"
            kill -s "${CAT_INTERRUPT}" $$
        else
            command cat "$input"
        fi
    else
        command cat "$input"
    fi
    command rm -f "$input"
}
SH;

        file_put_contents($this->root.'/stubs.sh', $stubs."\n");
    }

    private static function findExecutable(string $name): ?string
    {
        $directories = array_merge(['/bin', '/usr/bin', '/usr/local/bin', '/opt/homebrew/bin'], explode(PATH_SEPARATOR, (string) getenv('PATH')));

        foreach ($directories as $directory) {
            $candidate = rtrim($directory, '/').'/'.$name;

            if ($directory !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
