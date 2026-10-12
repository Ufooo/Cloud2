<?php

use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Nip\Security\Database\Factories\SecurityRuleCredentialFactory;
use Nip\Security\Database\Factories\SecurityRuleFactory;
use Nip\Security\Enums\SecurityRuleStatus;
use Nip\Security\Jobs\AddSecurityRuleJob;
use Nip\Security\Jobs\RemoveSecurityRuleJob;
use Nip\Security\Jobs\UpdateSecurityRuleJob;
use Nip\Security\Models\SecurityRule;
use Nip\Server\Jobs\BaseProvisionJob;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Models\Site;
use PHPUnit\Framework\Assert;

require_once __DIR__.'/Support/helpers.php';

/*
 * These jobs are never dispatched and never handled here: handle() opens an SSH connection, and this
 * local instance is wired to the production servers. Only their protected hooks are called, directly,
 * which is how the repo tests jobs elsewhere (see AddDomainJobTest).
 */

beforeEach(function () {
    // Nothing below dispatches or handles a job, and this makes that structural: with QUEUE_CONNECTION=sync an
    // accidental dispatch would otherwise run the job and SSH into the server of the (production-wired) fixture.
    Queue::fake();
    $this->mock(SSHService::class)->shouldNotReceive('connect');

    $this->server = Server::factory()->connected()->create();
    $this->site = Site::factory()->for($this->server)->laravel()->installed()->create(['domain' => 'example.com']);
});

function securityJobCall(object $job, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionClass($job))->getMethod($method)->invoke($job, ...$arguments);
}

/**
 * @param  array<string, mixed>  $attributes
 * @param  list<string>  $usernames
 */
function securityJobRule(Site $site, array $attributes = [], array $usernames = ['anna']): SecurityRule
{
    $rule = SecurityRuleFactory::new()->for($site)->create($attributes);

    foreach ($usernames as $username) {
        SecurityRuleCredentialFactory::new()->for($rule)->create(['username' => $username]);
    }

    return $rule;
}

function securityJobSuccess(): ExecutionResult
{
    return new ExecutionResult(output: 'ok', exitCode: 0, duration: 1.0);
}

it('is a provision job that runs on the server of the rule site', function (string $jobClass) {
    $rule = securityJobRule($this->site);
    $job = new $jobClass($rule);

    expect($job)->toBeInstanceOf(BaseProvisionJob::class)
        ->and(securityJobCall($job, 'getResourceType'))->toBe('security')
        ->and(securityJobCall($job, 'getResourceId'))->toBe($rule->id)
        ->and(securityJobCall($job, 'getServer')->is($this->server))->toBeTrue();
})->with([
    'add' => AddSecurityRuleJob::class,
    'update' => UpdateSecurityRuleJob::class,
    'remove' => RemoveSecurityRuleJob::class,
]);

it('tags the job so horizon can group it', function (string $jobClass, string $action) {
    $rule = securityJobRule($this->site);

    expect((new $jobClass($rule))->tags())->toBe([
        'provision',
        'security',
        "security:{$rule->id}",
        "site:{$this->site->id}",
        "server:{$this->server->id}",
        $action,
    ]);
})->with([
    'add' => [AddSecurityRuleJob::class, 'add'],
    'update' => [UpdateSecurityRuleJob::class, 'update'],
    'remove' => [RemoveSecurityRuleJob::class, 'remove'],
]);

it('marks the rule installed once an add or update has run on the server', function (string $jobClass, SecurityRuleStatus $before) {
    $rule = securityJobRule($this->site, ['status' => $before]);

    securityJobCall(new $jobClass($rule), 'handleSuccess', securityJobSuccess());

    expect($rule->fresh()->status)->toBe(SecurityRuleStatus::Installed);
})->with([
    'add' => [AddSecurityRuleJob::class, SecurityRuleStatus::Installing],
    'update' => [UpdateSecurityRuleJob::class, SecurityRuleStatus::Updating],
]);

it('marks the rule failed when the job gives up', function (string $jobClass, SecurityRuleStatus $before) {
    $rule = securityJobRule($this->site, ['status' => $before]);

    securityJobCall(new $jobClass($rule), 'handleFailure', new RuntimeException('nginx -t failed'));

    expect($rule->fresh()->status)->toBe(SecurityRuleStatus::Failed);
})->with([
    'add' => [AddSecurityRuleJob::class, SecurityRuleStatus::Installing],
    'update' => [UpdateSecurityRuleJob::class, SecurityRuleStatus::Updating],
    'remove' => [RemoveSecurityRuleJob::class, SecurityRuleStatus::Removing],
]);

it('deletes the rule and its credentials only after the removal succeeded, and nothing else', function () {
    $removed = securityJobRule($this->site, ['status' => SecurityRuleStatus::Removing, 'path' => '/admin'], ['gone']);
    $sibling = securityJobRule($this->site, ['path' => '/api'], ['stays']);
    $foreign = securityJobRule(
        Site::factory()->for($this->server)->laravel()->installed()->create(),
        [],
        ['foreign'],
    );

    securityJobCall(new RemoveSecurityRuleJob($removed), 'handleSuccess', securityJobSuccess());

    $this->assertModelMissing($removed);
    $this->assertDatabaseMissing('security_rule_credentials', ['username' => 'gone']);

    $this->assertModelExists($sibling);
    $this->assertModelExists($foreign);
    $this->assertDatabaseHas('security_rule_credentials', ['username' => 'stays']);
    $this->assertDatabaseHas('security_rule_credentials', ['username' => 'foreign']);
});

it('keeps the rule, marked failed, when the removal fails', function () {
    $rule = securityJobRule($this->site, ['status' => SecurityRuleStatus::Removing]);

    securityJobCall(new RemoveSecurityRuleJob($rule), 'handleFailure', new RuntimeException('boom'));

    $this->assertModelExists($rule);
    expect($rule->fresh()->status)->toBe(SecurityRuleStatus::Failed);
});

it('syncs every rule of the site except the ones being removed', function (string $jobClass) {
    $trigger = securityJobRule($this->site, ['name' => 'Trigger'], ['keep-user']);
    securityJobRule($this->site, ['name' => 'Admin', 'path' => '/admin'], ['admin-user']);
    $leaving = securityJobRule(
        $this->site,
        ['name' => 'Leaving', 'path' => '/gone', 'status' => SecurityRuleStatus::Removing],
        ['gone-user'],
    );

    $script = securityJobCall(new $jobClass($trigger), 'generateScript');

    expect($script)
        ->toContain('keep-user:')
        ->toContain('admin-user:')
        ->toContain('location = /admin')
        ->not->toContain('gone-user')
        ->not->toContain('/gone')
        ->not->toContain("htpasswd/{$leaving->id};")
        ->not->toContain("HTPASSWDEOF{$leaving->id}'");
})->with([
    'add' => AddSecurityRuleJob::class,
    'update' => UpdateSecurityRuleJob::class,
]);

it('syncs the remaining rules without the one being removed', function (SecurityRuleStatus $status) {
    $removed = securityJobRule($this->site, ['name' => 'Removed', 'path' => '/admin', 'status' => $status], ['gone-user']);
    securityJobRule($this->site, ['name' => 'Stays'], ['stay-user']);

    $script = securityJobCall(new RemoveSecurityRuleJob($removed), 'generateScript');

    expect($script)
        ->toContain('stay-user:')
        ->toContain('auth_basic "Stays";')
        ->not->toContain('gone-user')
        ->not->toContain('location = /admin')
        ->not->toContain("htpasswd/{$removed->id};");
})->with([
    'a rule already marked removing' => SecurityRuleStatus::Removing,
    'a retry of a rule whose removal failed before' => SecurityRuleStatus::Failed,
]);

it('turns the sync into a clean-up when the last rule is removed', function () {
    $removed = securityJobRule($this->site, ['status' => SecurityRuleStatus::Removing], ['gone-user']);

    $script = securityJobCall(new RemoveSecurityRuleJob($removed), 'generateScript');

    expect($script)
        ->toContain('rm -f "$AUTH_CONF"')
        ->not->toMatch('/^[ \t]*auth_basic(_user_file)?[ \t]/m')
        ->not->toContain('gone-user')
        ->not->toContain('HTPASSWDEOF');

    // The nginx -t failure branch has its own rm; the clean-up must come first, in the main flow.
    expect(securityScriptCommandOffset($script, 'rm -f "$AUTH_CONF"'))
        ->toBeLessThan(securityScriptCommandOffset($script, 'nginx -t'));
});

it('never lets the rules of another site into the script', function (string $jobClass) {
    $other = Site::factory()->for($this->server)->laravel()->installed()->create(['domain' => 'other.example.net']);
    securityJobRule($other, ['name' => 'Foreign', 'path' => '/secret'], ['intruder']);
    $mine = securityJobRule($this->site, [], ['anna']);

    $script = securityJobCall(new $jobClass($mine), 'generateScript');

    expect($script)
        ->not->toContain('intruder')
        ->not->toContain('/secret')
        ->not->toContain('Foreign')
        ->not->toContain('other.example.net');
})->with([
    'add' => AddSecurityRuleJob::class,
    'update' => UpdateSecurityRuleJob::class,
    'remove' => RemoveSecurityRuleJob::class,
]);

it('loads the credentials of all rules with a single query', function (string $jobClass) {
    $trigger = securityJobRule($this->site, [], ['a1', 'a2']);
    securityJobRule($this->site, ['path' => '/one'], ['b1', 'b2']);
    securityJobRule($this->site, ['path' => '/two'], ['c1', 'c2']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    securityJobCall(new $jobClass($trigger), 'generateScript');
    DB::disableQueryLog();

    $credentialQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query) => str_contains($query['query'], 'security_rule_credentials'));

    expect($credentialQueries)->toHaveCount(1);
})->with([
    'add' => AddSecurityRuleJob::class,
    'update' => UpdateSecurityRuleJob::class,
    'remove' => RemoveSecurityRuleJob::class,
]);

function securityOverlapMiddleware(object $job): WithoutOverlapping
{
    $middleware = collect($job->middleware())->first(fn ($candidate) => $candidate instanceof WithoutOverlapping);

    Assert::assertInstanceOf(WithoutOverlapping::class, $middleware, $job::class.' has no WithoutOverlapping middleware');

    return $middleware;
}

describe('one sync at a time per site', function () {
    // The rule set is read when the script is generated, before the SSH connection is made, so a job that read it early
    // can run last and write an outdated state; two scripts that run at once also share one auth.conf.prev and one of
    // them tests the other one's file. The lock has to cover reading AND running, which is what job middleware does.

    it('gives the add, update and remove job of a site one and the same lock, and another site another one', function () {
        $other = Site::factory()->for($this->server)->laravel()->installed()->create(['domain' => 'other.example.net']);

        $jobs = [
            new AddSecurityRuleJob(securityJobRule($this->site)),
            new UpdateSecurityRuleJob(securityJobRule($this->site, ['path' => '/admin'])),
            new RemoveSecurityRuleJob(securityJobRule($this->site, ['path' => '/api'])),
        ];
        $elsewhere = new AddSecurityRuleJob(securityJobRule($other));

        $keys = array_map(fn (object $job) => securityOverlapMiddleware($job)->getLockKey($job), $jobs);

        expect(array_unique($keys))->toHaveCount(1)
            ->and(securityOverlapMiddleware($elsewhere)->getLockKey($elsewhere))->not->toBe($keys[0]);
    });

    it('shares the key, because otherwise the lock name carries the class and the three jobs stop excluding each other', function (string $jobClass) {
        $job = new $jobClass(securityJobRule($this->site));

        expect(securityOverlapMiddleware($job)->shareKey)->toBeTrue();
    })->with([
        'add' => AddSecurityRuleJob::class,
        'update' => UpdateSecurityRuleJob::class,
        'remove' => RemoveSecurityRuleJob::class,
    ]);

    it('lets the lock expire only after the longest time a sync can run, never not at all', function (string $jobClass) {
        $job = new $jobClass(securityJobRule($this->site));
        $middleware = securityOverlapMiddleware($job);

        // The default is 0, which means the lock never expires: one killed worker would block the site for good.
        expect($middleware->expiresAfter)->toBeGreaterThan(0)
            ->and($middleware->expiresAfter)->toBeGreaterThan(securityJobCall($job, 'getSshTimeout'));
    })->with([
        'add' => AddSecurityRuleJob::class,
        'update' => UpdateSecurityRuleJob::class,
        'remove' => RemoveSecurityRuleJob::class,
    ]);

    it('puts a job that has to wait back on the queue after a pause, neither spinning nor dropping it', function (string $jobClass) {
        $job = new $jobClass(securityJobRule($this->site));

        // 0 would retry at once in a loop, null (dontRelease) would throw the job away silently.
        expect(securityOverlapMiddleware($job)->releaseAfter)->toBeInt()->toBeGreaterThan(0);
    })->with([
        'add' => AddSecurityRuleJob::class,
        'update' => UpdateSecurityRuleJob::class,
        'remove' => RemoveSecurityRuleJob::class,
    ]);

    it('keeps retrying for as long as a lock can hold, instead of using up its tries on the waiting', function (string $jobClass) {
        // Every release because of the lock counts as an attempt; with the default of three tries a job that
        // waited twice would fail with MaxAttemptsExceeded, and the rule would read Failed although nothing ever ran.
        $job = new $jobClass(securityJobRule($this->site));
        $until = $job->retryUntil();

        expect($until)->toBeInstanceOf(DateTimeInterface::class)
            ->and($until->getTimestamp())->toBeGreaterThan(now()->addSeconds(securityOverlapMiddleware($job)->expiresAfter)->getTimestamp());
    })->with([
        'add' => AddSecurityRuleJob::class,
        'update' => UpdateSecurityRuleJob::class,
        'remove' => RemoveSecurityRuleJob::class,
    ]);

    it('makes a job of the same site wait while another one runs, but not a job of another site', function (string $running, string $waiting) {
        $other = Site::factory()->for($this->server)->laravel()->installed()->create(['domain' => 'other.example.net']);

        $first = new $running(securityJobRule($this->site));
        $second = new $waiting(securityJobRule($this->site, ['path' => '/admin']));
        $elsewhere = new $running(securityJobRule($other));

        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('release')->once()->with(Mockery::on(fn ($delay) => $delay > 0));
        $second->setJob($queueJob);

        $ran = [];

        securityOverlapMiddleware($first)->handle($first, function () use ($second, $elsewhere, &$ran) {
            $ran[] = 'first';

            // While the first sync is in progress: the same site has to wait, another site does not.
            securityOverlapMiddleware($second)->handle($second, function () use (&$ran) {
                $ran[] = 'second';
            });
            securityOverlapMiddleware($elsewhere)->handle($elsewhere, function () use (&$ran) {
                $ran[] = 'elsewhere';
            });
        });

        expect($ran)->toBe(['first', 'elsewhere']);

        // The lock is gone once the first sync is done, so the waiting job gets its turn on the next attempt.
        securityOverlapMiddleware($second)->handle($second, function () use (&$ran) {
            $ran[] = 'second';
        });

        expect($ran)->toBe(['first', 'elsewhere', 'second']);
    })->with([
        'update waits for add' => [AddSecurityRuleJob::class, UpdateSecurityRuleJob::class],
        'remove waits for add' => [AddSecurityRuleJob::class, RemoveSecurityRuleJob::class],
        'add waits for update' => [UpdateSecurityRuleJob::class, AddSecurityRuleJob::class],
        'remove waits for update' => [UpdateSecurityRuleJob::class, RemoveSecurityRuleJob::class],
        'add waits for remove' => [RemoveSecurityRuleJob::class, AddSecurityRuleJob::class],
        'update waits for remove' => [RemoveSecurityRuleJob::class, UpdateSecurityRuleJob::class],
    ]);
});
