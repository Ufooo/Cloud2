<?php

use Nip\Security\Database\Factories\SecurityRuleCredentialFactory;
use Nip\Security\Database\Factories\SecurityRuleFactory;
use Nip\Security\Models\SecurityRule;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;
use PHPUnit\Framework\Assert;

/*
 * Shared helpers of the security rule tests. Every function is prefixed "security" because Pest loads
 * all test files into one process, where global function names must stay unique.
 */

/**
 * @param  array<string, mixed>  $attributes
 */
function securityScriptSite(string $state = 'laravel', array $attributes = []): Site
{
    return Site::factory()
        ->for(Server::factory()->connected())
        ->{$state}()
        ->installed()
        ->create(array_merge(['domain' => 'example.com'], $attributes));
}

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<string, string>  $credentials  username => plain-text password
 */
function securityScriptRule(Site $site, array $attributes = [], array $credentials = ['anna' => 's3cret-pass']): SecurityRule
{
    $rule = SecurityRuleFactory::new()->for($site)->create($attributes);

    foreach ($credentials as $username => $password) {
        SecurityRuleCredentialFactory::new()->for($rule)->withPassword($password)->create(['username' => $username]);
    }

    return $rule->load('credentials');
}

/**
 * Renders the view with every rule of the site, credentials eager-loaded, ordered by id. The jobs feed it the same
 * way except that they leave out rules in the `removing` state (and the one being removed): the job tests cover
 * that filter through generateScript(), this helper does not apply it.
 */
function renderSecuritySync(Site $site): string
{
    return view('provisioning.scripts.security.sync', [
        'site' => $site,
        'rules' => $site->securityRules()->with('credentials')->orderBy('id')->get(),
    ])->render();
}

/**
 * @return list<string>
 */
function securityScriptScenarios(bool $withEmpty = true): array
{
    $scenarios = [
        'a site-wide rule',
        'a path rule on a php site',
        'a path rule on a static site',
        'several rules',
    ];

    return $withEmpty ? ['no rules', ...$scenarios] : $scenarios;
}

function securityScriptScenario(string $scenario): Site
{
    $site = securityScriptSite($scenario === 'a path rule on a static site' ? 'static' : 'laravel');

    match ($scenario) {
        'no rules' => null,
        'a site-wide rule' => securityScriptRule($site, ['name' => 'Staging']),
        'a path rule on a php site' => securityScriptRule($site, ['name' => 'Admin area', 'path' => '/admin']),
        'a path rule on a static site' => securityScriptRule($site, ['name' => 'Private', 'path' => '/private']),
        'several rules' => [
            securityScriptRule($site, ['name' => 'Staging']),
            securityScriptRule($site, ['name' => 'Admin area', 'path' => '/admin'], ['anna' => 'pw-one', 'bela' => 'pw-two']),
            securityScriptRule($site, ['name' => 'Api', 'path' => '/api/private'], ['carl' => 'pw-three']),
        ],
    };

    return $site;
}

/**
 * The nginx file the script writes: the body of its single quoted AUTHEOF heredoc.
 */
function securityAuthConf(string $script): string
{
    $found = preg_match("/<<[ \t]*'AUTHEOF'\R(.*?)\RAUTHEOF\b/s", $script, $matches);

    Assert::assertSame(1, $found, 'The script does not write auth.conf through a quoted AUTHEOF heredoc.');

    return $matches[1];
}

/**
 * The lines a rule's htpasswd heredoc writes.
 *
 * @return list<string>
 */
function securityHtpasswdLines(string $script, SecurityRule $rule): array
{
    $found = preg_match("/<<[ \t]*'HTPASSWDEOF{$rule->id}'\R(.*?)\RHTPASSWDEOF{$rule->id}\b/s", $script, $matches);

    Assert::assertSame(1, $found, "The script has no quoted heredoc for the htpasswd file of rule {$rule->id}.");

    return explode("\n", $matches[1]);
}

/**
 * Offset of the first line that runs $command. Comment lines that merely mention it do not count,
 * otherwise an explanatory "# ... nginx -t ..." in the script header would be taken for the command.
 */
function securityScriptCommandOffset(string $script, string $command): int|false
{
    // A command that ends in a letter or digit must not be the start of a longer word: `exit 1` is not `exit 143`.
    $boundary = preg_match('/\w$/', $command) === 1 ? '(?!\w)' : '';

    if (preg_match('/^[ \t]*+(?!#)[^\n]*'.preg_quote($command, '/').$boundary.'/m', $script, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return false;
    }

    return $match[0][1];
}

/**
 * Splits an nginx snippet into its top-level location blocks (header => body) and everything else.
 * A brace counter is enough here: the generated snippets keep no braces inside quoted strings.
 *
 * @return array{blocks: array<string, string>, rest: string}
 */
function securityNginxParse(string $conf): array
{
    $blocks = [];
    $rest = '';
    $offset = 0;

    while (preg_match('/^[ \t]*location[ \t]+([^{};\r\n]+?)[ \t]*\{/m', $conf, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $rest .= substr($conf, $offset, $match[0][1] - $offset);

        $bodyStart = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $position = $bodyStart;

        while ($position < strlen($conf) && $depth > 0) {
            $depth += match ($conf[$position]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
            $position++;
        }

        $blocks[preg_replace('/\s+/', ' ', trim($match[1][0]))] = substr($conf, $bodyStart, $position - $bodyStart - 1);
        $offset = $position;
    }

    return ['blocks' => $blocks, 'rest' => $rest.substr($conf, $offset)];
}

/**
 * The script stops with an understandable message before it changes anything: the first `exit 1` is a guard, a
 * message matching $messagePattern is printed no later than that line, and every command that writes or deletes
 * comes after it.
 */
function securityAssertStopsBeforeWriting(string $script, string $messagePattern): void
{
    $guard = securityScriptCommandOffset($script, 'exit 1');

    expect($guard)->not->toBeFalse();

    $untilExit = substr($script, 0, (int) strpos($script, "\n", (int) $guard));

    expect($untilExit)->toMatch($messagePattern);

    preg_match_all('/^[ \t]*+(?:mkdir|cat|cp|mv|rm|chown|chmod|find|umask|touch|tee|install)\b/m', $script, $writes, PREG_OFFSET_CAPTURE);

    foreach ($writes[0] as [, $offset]) {
        expect($offset)->toBeGreaterThan($guard);
    }
}
