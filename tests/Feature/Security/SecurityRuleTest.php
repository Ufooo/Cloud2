<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Nip\Security\Database\Factories\SecurityRuleCredentialFactory;
use Nip\Security\Database\Factories\SecurityRuleFactory;
use Nip\Security\Enums\SecurityRuleStatus;
use Nip\Security\Jobs\AddSecurityRuleJob;
use Nip\Security\Jobs\RemoveSecurityRuleJob;
use Nip\Security\Jobs\UpdateSecurityRuleJob;
use Nip\Security\Models\SecurityRule;
use Nip\Security\Models\SecurityRuleCredential;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Models\Site;

/*
 * The security rule factories are used through their own ::new() on purpose: the models' HasFactory
 * wiring is a separate concern and must not decide whether these tests can even build their data.
 */

beforeEach(function () {
    // QUEUE_CONNECTION=sync in phpunit.xml: a dispatch without the fake would run the job right here and
    // SSH into the server. This local instance is wired to production, so the fake is not optional.
    Queue::fake();
    $this->mock(SSHService::class)->shouldNotReceive('connect');

    $this->user = User::factory()->create();
    $this->server = Server::factory()->connected()->create();
    $this->site = Site::factory()->for($this->server)->laravel()->installed()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function securityRulePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Staging',
        'credentials' => [
            ['username' => 'anna', 'password' => 's3cret-pass'],
        ],
    ], $overrides);
}

/**
 * Paths that must never reach a location directive. The first group could break out of the directive or the file; the
 * second group is syntactically harmless but makes a dead rule or opens what the site keeps shut (see the explanation
 * in the test that uses the dotfile ones).
 *
 * @return array<string, array{string}>
 */
function securityRejectedPaths(): array
{
    return [
        'no leading slash' => ['admin'],
        'a space' => ['/ad min'],
        'a semicolon ends the nginx directive' => ['/admin;'],
        'braces open a new block' => ['/admin { return 200; }'],
        'a double quote' => ['/admin"'],
        'a dollar sign starts an nginx variable' => ['/admin$uri'],
        'a newline inside' => ["/admin\nlocation / { }"],
        'a hash starts an nginx comment' => ['/admin#'],
        'a backslash' => ['/admin\\x'],
        // The newline survives the trimming of the request because the value ends in a slash; cutting that slash
        // leaves "/admin\n", which a regex without the D modifier accepts at its "$".
        'a newline in front of the trailing slash' => ["/admin\n/"],
        'a dot segment in front' => ['/./admin'],
        'a parent segment at the end' => ['/admin/..'],
        'a parent segment inside' => ['/admin/../etc'],
        'only a dot' => ['/.'],
        'a dotfile at the root' => ['/.env'],
        'a dotfile below a directory' => ['/admin/.env'],
        'a hidden directory' => ['/.git/config'],
        'a hidden file next to a normal one' => ['/admin/.htpasswd'],
    ];
}

/**
 * Usernames that cannot work in a htpasswd file: the line is "user:hash", and a username is one token.
 * Leading and trailing whitespace is left out on purpose, the framework trims that before validation.
 *
 * @return array<string, array{string}>
 */
function securityRejectedUsernames(): array
{
    return [
        'a colon in the middle' => ['an:na'],
        'a leading colon' => [':anna'],
        'a trailing colon' => ['anna:'],
        'a space' => ['an na'],
        'a tab' => ["an\tna"],
        'a line break' => ["an\nna"],
        'a carriage return' => ["an\rna"],
    ];
}

/**
 * Punctuation that is fine in a username and must not be refused or altered.
 *
 * @return array<string, array{string}>
 */
function securityAcceptedUsernames(): array
{
    return [
        'an apostrophe and an ampersand' => ["o'neil&co"],
        'an e-mail address' => ['anna@example.com'],
        'dots, dashes, underscores and a plus' => ['anna.o-neil_1+tag'],
    ];
}

describe('store', function () {
    it('creates the rule as installing and dispatches AddSecurityRuleJob', function () {
        $this->actingAs($this->user)
            ->post(route('sites.security.store', $this->site), securityRulePayload(['name' => 'Staging']))
            ->assertRedirect(route('sites.security', $this->site))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'being installed'));

        $rule = SecurityRule::query()->where('site_id', $this->site->id)->sole();

        expect($rule->name)->toBe('Staging')
            ->and($rule->status)->toBe(SecurityRuleStatus::Installing);

        $this->assertDatabaseHas('security_rules', ['id' => $rule->id, 'status' => 'installing']);

        Queue::assertPushed(AddSecurityRuleJob::class, 1);
        Queue::assertPushed(AddSecurityRuleJob::class, fn (AddSecurityRuleJob $job) => $job->securityRule->is($rule));
        Queue::assertNotPushed(UpdateSecurityRuleJob::class);
        Queue::assertNotPushed(RemoveSecurityRuleJob::class);
    });

    it('stores the credentials as bcrypt cost 5 hashes with the $2y$ prefix', function () {
        // Pin the application default to something other than 5, so only an explicit
        // ['rounds' => 5] on the bcrypt driver can produce cost 5.
        config(['hashing.bcrypt.rounds' => 10]);
        Hash::forgetDrivers();

        $this->actingAs($this->user)
            ->post(route('sites.security.store', $this->site), securityRulePayload([
                'credentials' => [
                    ['username' => 'anna', 'password' => 's3cret-pass'],
                    ['username' => 'bela', 'password' => 'another-pass'],
                ],
            ]))
            ->assertRedirect();

        $credentials = SecurityRuleCredential::query()->orderBy('username')->get();

        expect($credentials)->toHaveCount(2);

        foreach ($credentials as $credential) {
            $info = password_get_info($credential->password);

            expect($credential->password)->toStartWith('$2y$05$')
                ->and($info['algoName'])->toBe('bcrypt')
                ->and($info['options']['cost'])->toBe(5);
        }

        expect(password_verify('s3cret-pass', $credentials[0]->password))->toBeTrue()
            ->and(password_verify('another-pass', $credentials[1]->password))->toBeTrue();
    });

    it('keeps bcrypt even when the application default hasher is argon2', function () {
        // nginx verifies the htpasswd entries with crypt(), which cannot read an argon2 hash.
        config(['hashing.driver' => 'argon2id']);
        Hash::forgetDrivers();

        $this->actingAs($this->user)
            ->post(route('sites.security.store', $this->site), securityRulePayload())
            ->assertRedirect();

        $hash = SecurityRuleCredential::query()->sole()->password;

        expect($hash)->toStartWith('$2y$05$')
            ->and(password_get_info($hash)['algoName'])->toBe('bcrypt')
            ->and(password_verify('s3cret-pass', $hash))->toBeTrue();
    })->skip(! defined('PASSWORD_ARGON2ID'), 'argon2id is not available in this PHP build');

    it('normalizes the path before storing it', function (?string $submitted, ?string $stored) {
        $this->actingAs($this->user)
            ->post(route('sites.security.store', $this->site), securityRulePayload(['path' => $submitted]))
            ->assertRedirect(route('sites.security', $this->site));

        expect(SecurityRule::query()->sole()->path)->toBe($stored);
    })->with([
        'trailing slash is cut' => ['/admin/', '/admin'],
        'plain path stays as it is' => ['/admin', '/admin'],
        'nested path loses its trailing slash' => ['/admin/users/', '/admin/users'],
        'root means site-wide' => ['/', null],
        'empty means site-wide' => ['', null],
        'null means site-wide' => [null, null],
        // nginx merges repeated slashes in the request URI before it matches a location, so the stored path has to be
        // the merged one: a location written with "//" in it never matches anything.
        'repeated slashes in front are merged' => ['//admin', '/admin'],
        'repeated slashes inside are merged' => ['/admin//x', '/admin/x'],
        'repeated trailing slashes are merged and cut' => ['/admin//', '/admin'],
        'a run of slashes is the root' => ['///', null],
        'merging comes before cutting' => ['/a///b//', '/a/b'],
    ]);

    it('rejects a path that collides with an existing rule of the same site', function (?string $existing, ?string $submitted) {
        SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['path' => $existing]);

        $this->actingAs($this->user)
            ->postJson(route('sites.security.store', $this->site), securityRulePayload(['path' => $submitted]))
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors('path');

        expect(SecurityRule::query()->count())->toBe(1);
        Queue::assertNothingPushed();
    })->with([
        'a second site-wide rule' => [null, null],
        'root, which normalizes to site-wide' => [null, '/'],
        'the same path twice' => ['/admin', '/admin'],
        'the same path with a trailing slash' => ['/admin', '/admin/'],
    ]);

    it('accepts paths that merely resemble an existing one', function (?string $existing, ?string $submitted) {
        SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['path' => $existing]);

        $this->actingAs($this->user)
            ->post(route('sites.security.store', $this->site), securityRulePayload(['path' => $submitted]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.security', $this->site));

        expect(SecurityRule::query()->count())->toBe(2);
        Queue::assertPushed(AddSecurityRuleJob::class, 1);
    })->with([
        'a longer sibling name' => ['/admin', '/administrator'],
        'a nested path' => ['/admin', '/admin/users'],
        'an unrelated path' => ['/admin', '/api/private'],
        'a path next to a site-wide rule' => [null, '/admin'],
        'a site-wide rule next to a path' => ['/admin', null],
    ]);

    it('scopes the uniqueness checks to the site', function () {
        $otherSite = Site::factory()->for($this->server)->laravel()->installed()->create();
        SecurityRuleFactory::new()->for($otherSite)->withCredentials()->create(['path' => null]);
        SecurityRuleFactory::new()->for($otherSite)->withCredentials()->withPath('/admin')->create();

        $this->actingAs($this->user)
            ->post(route('sites.security.store', $this->site), securityRulePayload(['name' => 'Site-wide', 'path' => null]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->post(route('sites.security.store', $this->site), securityRulePayload(['name' => 'Admin', 'path' => '/admin']))
            ->assertSessionHasNoErrors();

        expect($this->site->securityRules()->count())->toBe(2)
            ->and($otherSite->securityRules()->count())->toBe(2);
    });

    it('rejects paths that are not a plain URL path', function (string $path) {
        // The path is written verbatim into an nginx location, so anything that could close the
        // directive or open a block has to be refused at the door.
        $this->actingAs($this->user)
            ->postJson(route('sites.security.store', $this->site), securityRulePayload(['path' => $path]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('path');

        expect(SecurityRule::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with(securityRejectedPaths());

    it('refuses a path that starts a dotfile, because an exact location would out-rank the site\'s own dotfile deny', function (string $path) {
        // This is not cosmetic validation. Every site block carries `location ~ /\.(?!well-known).* { deny all; }`.
        // A rule for "/.env" renders `location = /.env { auth_basic ...; try_files ...; }`, and an exact match ends
        // the location search before any regex location is tried, so the deny never runs. Measured with nginx 1.25.4
        // (GET with valid credentials): without a rule /.env and /admin/.env answer 403, with a rule for that very path
        // they answer 200 with the content of the file. Whoever knows the password would be handed files that nobody
        // was ever meant to serve.
        $this->actingAs($this->user)
            ->postJson(route('sites.security.store', $this->site), securityRulePayload(['path' => $path]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('path');

        expect(SecurityRule::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with([
        'the .env file at the root' => ['/.env'],
        'the .env file of a protected directory' => ['/admin/.env'],
        'a repository' => ['/.git/config'],
        'a password file' => ['/admin/.htpasswd'],
    ]);

    it('rejects usernames that cannot work in a htpasswd file', function (string $username) {
        // Cutting the character out silently would change the name somebody logs in with, so it is refused.
        $this->actingAs($this->user)
            ->postJson(route('sites.security.store', $this->site), securityRulePayload([
                'credentials' => [['username' => $username, 'password' => 's3cret-pass']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credentials.0.username');

        expect(SecurityRule::query()->count())->toBe(0)
            ->and(SecurityRuleCredential::query()->count())->toBe(0);

        Queue::assertNothingPushed();
    })->with(securityRejectedUsernames());

    it('stores usernames with other punctuation exactly as they were entered', function (string $username) {
        $this->actingAs($this->user)
            ->post(route('sites.security.store', $this->site), securityRulePayload([
                'credentials' => [['username' => $username, 'password' => 's3cret-pass']],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.security', $this->site));

        expect(SecurityRuleCredential::query()->sole()->username)->toBe($username);
    })->with(securityAcceptedUsernames());

    it('reports missing input with the validation error shape', function (array $payload, string $errorKey) {
        $this->actingAs($this->user)
            ->postJson(route('sites.security.store', $this->site), $payload)
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => [$errorKey]]);

        expect(SecurityRule::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with([
        'no name' => [['credentials' => [['username' => 'anna', 'password' => 'pw']]], 'name'],
        'no credentials' => [['name' => 'Staging'], 'credentials'],
        'an empty credentials list' => [['name' => 'Staging', 'credentials' => []], 'credentials'],
        'a credential without a username' => [['name' => 'Staging', 'credentials' => [['password' => 'pw']]], 'credentials.0.username'],
        'a credential without a password' => [['name' => 'Staging', 'credentials' => [['username' => 'anna']]], 'credentials.0.password'],
    ]);

    it('sends the browser back with session errors instead of creating the rule', function () {
        SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['path' => '/admin']);

        $this->actingAs($this->user)
            ->post(route('sites.security.store', $this->site), securityRulePayload(['path' => '/admin/']))
            ->assertSessionHasErrors('path');

        expect(SecurityRule::query()->count())->toBe(1);
        Queue::assertNothingPushed();
    });
});

describe('update', function () {
    it('marks the rule as updating and dispatches UpdateSecurityRuleJob', function () {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['name' => 'Old name']);

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $rule]), ['name' => 'New name'])
            ->assertRedirect(route('sites.security', $this->site))
            ->assertSessionHas('success');

        expect($rule->fresh())
            ->name->toBe('New name')
            ->status->toBe(SecurityRuleStatus::Updating);

        $this->assertDatabaseHas('security_rules', ['id' => $rule->id, 'status' => 'updating']);

        Queue::assertPushed(UpdateSecurityRuleJob::class, 1);
        Queue::assertPushed(UpdateSecurityRuleJob::class, fn (UpdateSecurityRuleJob $job) => $job->securityRule->is($rule));
        Queue::assertNotPushed(AddSecurityRuleJob::class);
        Queue::assertNotPushed(RemoveSecurityRuleJob::class);
    });

    it('replaces the credentials with cost 5 bcrypt hashes when new ones are submitted', function () {
        config(['hashing.bcrypt.rounds' => 10]);
        Hash::forgetDrivers();

        $rule = SecurityRuleFactory::new()->for($this->site)->create();
        SecurityRuleCredentialFactory::new()->for($rule)->create(['username' => 'old-user']);

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $rule]), [
                'credentials' => [['username' => 'new-user', 'password' => 'brand-new-pass']],
            ])
            ->assertRedirect();

        $credential = $rule->credentials()->sole();

        expect($credential->username)->toBe('new-user')
            ->and($credential->password)->toStartWith('$2y$05$')
            ->and(password_get_info($credential->password)['options']['cost'])->toBe(5)
            ->and(password_verify('brand-new-pass', $credential->password))->toBeTrue();

        $this->assertDatabaseMissing('security_rule_credentials', ['username' => 'old-user']);
    });

    it('keeps the stored credentials and the path when only the name changes', function () {
        $rule = SecurityRuleFactory::new()->for($this->site)->withPath('/admin')->create(['name' => 'Old name']);
        $credential = SecurityRuleCredentialFactory::new()->for($rule)->create(['username' => 'anna']);
        $hash = $credential->password;

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $rule]), ['name' => 'Renamed'])
            ->assertRedirect();

        $stored = $rule->credentials()->sole();

        expect($stored->is($credential))->toBeTrue()
            ->and($stored->password)->toBe($hash)
            ->and($rule->fresh()->path)->toBe('/admin');
    });

    it('does not check the path when the request leaves it out', function () {
        // A rename must not be held up by the path rules of a field nobody touched: with a site-wide rule
        // next to it, a path rule that is merely renamed would otherwise look like a second site-wide one.
        SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['name' => 'Whole site', 'path' => null]);
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->withPath('/admin')->create(['name' => 'Admin']);

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $rule]), ['name' => 'Renamed'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.security', $this->site));

        expect($rule->fresh())
            ->name->toBe('Renamed')
            ->path->toBe('/admin');
    });

    it('turns a path rule into a site-wide rule when the path is set to "/"', function () {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->withPath('/admin')->create();

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $rule]), ['path' => '/'])
            ->assertRedirect();

        expect($rule->fresh()->path)->toBeNull();
    });

    it('normalizes the path of an update as well', function () {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->withPath('/admin')->create();

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $rule]), ['path' => '/backoffice/'])
            ->assertRedirect();

        expect($rule->fresh()->path)->toBe('/backoffice');
    });

    it('does not count the rule being edited as a conflict with itself', function (?string $path, ?string $submitted) {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['path' => $path]);

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $rule]), ['name' => 'Renamed', 'path' => $submitted])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.security', $this->site));

        Queue::assertPushed(UpdateSecurityRuleJob::class, 1);
    })->with([
        'a path rule that keeps its path' => ['/admin', '/admin'],
        'a path rule that resubmits its path with a trailing slash' => ['/admin', '/admin/'],
        'a site-wide rule that resubmits root' => [null, '/'],
        'a site-wide rule that resubmits nothing' => [null, null],
    ]);

    it('rejects an update that collides with another rule of the site', function (?string $otherPath, ?string $submitted) {
        SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['name' => 'Other', 'path' => $otherPath]);
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->withPath('/api')->create(['name' => 'Mine']);

        $this->actingAs($this->user)
            ->patchJson(route('sites.security.update', [$this->site, $rule]), ['path' => $submitted])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('path');

        expect($rule->fresh())
            ->path->toBe('/api')
            ->status->toBe(SecurityRuleStatus::Installed);

        Queue::assertNothingPushed();
    })->with([
        'onto another path rule' => ['/admin', '/admin/'],
        'onto the site-wide rule' => [null, '/'],
    ]);

    it('rejects the same paths on update', function (string $path) {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->withPath('/admin')->create();

        $this->actingAs($this->user)
            ->patchJson(route('sites.security.update', [$this->site, $rule]), ['path' => $path])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('path');

        expect($rule->fresh())
            ->path->toBe('/admin')
            ->status->toBe(SecurityRuleStatus::Installed);

        Queue::assertNothingPushed();
    })->with(securityRejectedPaths());

    it('merges repeated slashes on update as well', function () {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->withPath('/admin')->create();

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $rule]), ['path' => '//backoffice//users//'])
            ->assertSessionHasNoErrors();

        expect($rule->fresh()->path)->toBe('/backoffice/users');
    });

    it('rejects usernames that cannot work in a htpasswd file on update too', function (string $username) {
        $rule = SecurityRuleFactory::new()->for($this->site)->create();
        $credential = SecurityRuleCredentialFactory::new()->for($rule)->create(['username' => 'anna']);
        $hash = $credential->password;

        $this->actingAs($this->user)
            ->patchJson(route('sites.security.update', [$this->site, $rule]), [
                'credentials' => [['username' => $username, 'password' => 'brand-new-pass']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credentials.0.username');

        // The stored credentials are exactly what they were, and nothing is queued.
        $stored = $rule->credentials()->sole();

        expect($stored->is($credential))->toBeTrue()
            ->and($stored->password)->toBe($hash)
            ->and($rule->fresh()->status)->toBe(SecurityRuleStatus::Installed);

        Queue::assertNothingPushed();
    })->with(securityRejectedUsernames());

    it('stores usernames with other punctuation exactly as they were entered on update too', function (string $username) {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->create();

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $rule]), [
                'credentials' => [['username' => $username, 'password' => 'brand-new-pass']],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.security', $this->site));

        expect($rule->credentials()->sole()->username)->toBe($username);
    })->with(securityAcceptedUsernames());

    it('refuses to update a rule while a job is already working on it', function (SecurityRuleStatus $status) {
        // A rule that is being removed would otherwise be flipped back to updating under the running job.
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['name' => 'Untouched', 'status' => $status]);

        $response = $this->actingAs($this->user)
            ->patchJson(route('sites.security.update', [$this->site, $rule]), ['name' => 'Renamed'])
            ->assertForbidden();

        expect($response->json('message'))->toBeString()->not->toBeEmpty();

        expect($rule->fresh())
            ->name->toBe('Untouched')
            ->status->toBe($status);

        Queue::assertNothingPushed();
    })->with([
        'an installation in progress' => [SecurityRuleStatus::Installing],
        'a removal in progress' => [SecurityRuleStatus::Removing],
    ]);

    it('still updates a rule that no job is working on', function (SecurityRuleStatus $status) {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['name' => 'Old name', 'status' => $status]);

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $rule]), ['name' => 'New name'])
            ->assertRedirect(route('sites.security', $this->site));

        expect($rule->fresh())
            ->name->toBe('New name')
            ->status->toBe(SecurityRuleStatus::Updating);

        Queue::assertPushed(UpdateSecurityRuleJob::class, 1);
    })->with([
        'an installed rule' => [SecurityRuleStatus::Installed],
        'a failed rule, so that a broken one can be fixed' => [SecurityRuleStatus::Failed],
        'a rule that never got installed' => [SecurityRuleStatus::Pending],
    ]);

    it('refuses to update a rule that belongs to another site', function () {
        $otherSite = Site::factory()->for($this->server)->laravel()->installed()->create();
        $foreign = SecurityRuleFactory::new()->for($otherSite)->withCredentials()->create(['name' => 'Foreign']);

        $this->actingAs($this->user)
            ->patch(route('sites.security.update', [$this->site, $foreign]), ['name' => 'Hijacked'])
            ->assertForbidden();

        expect($foreign->fresh())
            ->name->toBe('Foreign')
            ->status->toBe(SecurityRuleStatus::Installed);

        Queue::assertNothingPushed();
    });
});

describe('destroy', function () {
    it('marks the rule as removing, keeps the row and dispatches RemoveSecurityRuleJob', function (SecurityRuleStatus $status) {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['status' => $status]);

        $this->actingAs($this->user)
            ->delete(route('sites.security.destroy', [$this->site, $rule]))
            ->assertRedirect(route('sites.security', $this->site))
            ->assertSessionHas('success');

        // The job deletes the row once the directives are gone from the server. Deleting here would
        // leave a live auth_basic in nginx with nothing left that could remove it.
        $this->assertDatabaseHas('security_rules', ['id' => $rule->id, 'status' => 'removing']);
        expect($rule->fresh()->status)->toBe(SecurityRuleStatus::Removing);

        Queue::assertPushed(RemoveSecurityRuleJob::class, 1);
        Queue::assertPushed(RemoveSecurityRuleJob::class, fn (RemoveSecurityRuleJob $job) => $job->securityRule->is($rule));
        Queue::assertNotPushed(AddSecurityRuleJob::class);
        Queue::assertNotPushed(UpdateSecurityRuleJob::class);
    })->with([
        'a rule that never got installed' => [SecurityRuleStatus::Pending],
        'an installed rule' => [SecurityRuleStatus::Installed],
        'a failed rule' => [SecurityRuleStatus::Failed],
    ]);

    it('refuses to delete a rule while a job is already working on it', function (SecurityRuleStatus $status) {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['status' => $status]);

        $this->actingAs($this->user)
            ->delete(route('sites.security.destroy', [$this->site, $rule]))
            ->assertForbidden();

        expect($rule->fresh()->status)->toBe($status);
        Queue::assertNothingPushed();
    })->with([
        'an installation in progress' => [SecurityRuleStatus::Installing],
        'a removal in progress' => [SecurityRuleStatus::Removing],
    ]);

    it('refuses to delete a rule that belongs to another site', function () {
        $otherSite = Site::factory()->for($this->server)->laravel()->installed()->create();
        $foreign = SecurityRuleFactory::new()->for($otherSite)->withCredentials()->create();

        $this->actingAs($this->user)
            ->delete(route('sites.security.destroy', [$this->site, $foreign]))
            ->assertForbidden();

        expect($foreign->fresh()->status)->toBe(SecurityRuleStatus::Installed);
        Queue::assertNothingPushed();
    });
});

describe('authorization', function () {
    it('sends guests to the login page and changes nothing', function () {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->create();

        $this->post(route('sites.security.store', $this->site), securityRulePayload())
            ->assertRedirect(route('login'));
        $this->patch(route('sites.security.update', [$this->site, $rule]), ['name' => 'Hijacked'])
            ->assertRedirect(route('login'));
        $this->delete(route('sites.security.destroy', [$this->site, $rule]))
            ->assertRedirect(route('login'));

        expect(SecurityRule::query()->count())->toBe(1)
            ->and($rule->fresh())
            ->name->not->toBe('Hijacked')
            ->status->toBe(SecurityRuleStatus::Installed);

        Queue::assertNothingPushed();
    });

    it('is wired to the update ability on the server', function () {
        // ServerPolicy allows every user today, so there is no real "foreign user" to test against.
        // What can be proven is that the endpoints really ask the gate: deny the ability for one
        // user and every write must stop at 403 with no row touched and nothing queued.
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['name' => 'Untouched']);
        $stranger = User::factory()->create();

        Gate::before(fn (User $user) => $user->is($stranger) ? false : null);

        $this->actingAs($stranger)
            ->post(route('sites.security.store', $this->site), securityRulePayload())
            ->assertForbidden();
        $this->actingAs($stranger)
            ->patch(route('sites.security.update', [$this->site, $rule]), ['name' => 'Hijacked'])
            ->assertForbidden();
        $this->actingAs($stranger)
            ->delete(route('sites.security.destroy', [$this->site, $rule]))
            ->assertForbidden();

        expect(SecurityRule::query()->count())->toBe(1)
            ->and($rule->fresh())
            ->name->toBe('Untouched')
            ->status->toBe(SecurityRuleStatus::Installed);

        Queue::assertNothingPushed();

        // The same request goes through for a user the gate lets pass, so the 403 above is the gate and
        // not some other failure of the endpoint.
        $this->actingAs($this->user)
            ->post(route('sites.security.store', $this->site), securityRulePayload(['name' => 'Allowed', 'path' => '/admin']))
            ->assertRedirect(route('sites.security', $this->site));

        expect(SecurityRule::query()->count())->toBe(2);
    });
});

describe('index', function () {
    it('lets rules be edited when installed or failed, not while a job is working on them', function (SecurityRuleStatus $status, bool $canUpdate) {
        SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['status' => $status]);

        $this->actingAs($this->user)
            ->get(route('sites.security', $this->site))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('sites/Security')
                ->where('rules.data.0.status', $status->value)
                ->where('rules.data.0.can.update', $canUpdate)
            );
    })->with([
        'installed' => [SecurityRuleStatus::Installed, true],
        'failed, so a broken rule can still be fixed' => [SecurityRuleStatus::Failed, true],
        'installing' => [SecurityRuleStatus::Installing, false],
        'updating' => [SecurityRuleStatus::Updating, false],
        'removing' => [SecurityRuleStatus::Removing, false],
    ]);

    it('does not offer delete while the rule is being installed or removed', function (SecurityRuleStatus $status, bool $canDelete) {
        SecurityRuleFactory::new()->for($this->site)->withCredentials()->create(['status' => $status]);

        $this->actingAs($this->user)
            ->get(route('sites.security', $this->site))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rules.data.0.can.delete', $canDelete));
    })->with([
        'installed' => [SecurityRuleStatus::Installed, true],
        'failed' => [SecurityRuleStatus::Failed, true],
        'installing' => [SecurityRuleStatus::Installing, false],
        'removing' => [SecurityRuleStatus::Removing, false],
    ]);

    it('never sends password hashes to the browser', function () {
        $rule = SecurityRuleFactory::new()->for($this->site)->withCredentials(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('sites.security', $this->site))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rules.data.0.credentials', 2)
                ->has('rules.data.0.credentials.0.username')
                ->missing('rules.data.0.credentials.0.password')
                ->missing('rules.data.0.credentials.1.password')
            );

        expect($response->getContent())->not->toContain('$2y$');
        expect($rule->credentials)->toHaveCount(2);
    });
});
