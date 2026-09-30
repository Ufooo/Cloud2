<?php

use Illuminate\Support\Facades\Bus;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Jobs\EnableSslJob;
use Nip\Domain\Jobs\UpdateDomainJob;
use Nip\Domain\Models\Certificate;
use Nip\Domain\Models\DomainRecord;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Jobs\Provisioning\ConfigureWwwRedirectJob;
use Nip\Site\Models\Site;

/*
 * Regression: https://www.<apex> answered 404 on a freshly provisioned site (bconsulting.hu).
 *
 * Three generators wrote the same www redirect, and the only correct one (the Domain path,
 * which also serves 443) never ran once a certificate was installed. Provisioning wrote a
 * port-80-only redirect into the shared site-level include, and enabling SSL sed-patched the
 * server block without ever handing www over to a redirect of its own.
 */

/**
 * @param  array<string, string>  $variables
 */
function wwwRedirectSubstitute(string $text, array $variables): string
{
    return preg_replace_callback(
        '/\$(?|\{(\w+)\}|(\w+))/',
        fn (array $match): string => $variables[$match[1]] ?? $match[0],
        $text,
    );
}

/**
 * The provisioning scripts build their paths from plain VAR="..." assignments
 * (NGINX_CONF_DIR, SITE_CONF_DIR, DOMAIN_CONF_DIR), so a literal path never shows up in the
 * rendered text. Expanding those assignments lets the assertions check where a file really
 * lands, however a template spells the path. Names that are never assigned ($request_uri,
 * $scheme, $host) are left alone, and quotes are dropped so a path is one plain token.
 */
function wwwRedirectExpandedShell(string $script): string
{
    $variables = [];

    preg_match_all(
        '/^[ \t]*([A-Za-z_]\w*)=(?|"([^"\n]*)"|([^\s"\'`()<>;&|]*))[ \t]*$/m',
        $script,
        $assignments,
        PREG_SET_ORDER,
    );

    foreach ($assignments as [, $name, $value]) {
        $variables[$name] = wwwRedirectSubstitute($value, $variables);
    }

    return str_replace(['"', "'"], '', wwwRedirectSubstitute($script, $variables));
}

/**
 * Every redirect.conf the script writes (shell redirection or tee), variables expanded.
 * A cleanup `rm -f` of the same file is deliberately not a write.
 *
 * @return array<int, string>
 */
function wwwRedirectConfWrites(string $script): array
{
    preg_match_all(
        '~(?:>>?|\btee(?:\s+-\w+)*)\s*(\S*/redirect\.conf)(?=\s|;|&|\||<|$)~',
        wwwRedirectExpandedShell($script),
        $matches,
    );

    return $matches[1];
}

/**
 * Every path the script passes to rm, variables expanded.
 *
 * @return array<int, string>
 */
function wwwRedirectRemovedFiles(string $script): array
{
    preg_match_all('~\brm\s+(?:-\w+\s+)*(\S+)~', wwwRedirectExpandedShell($script), $matches);

    return $matches[1];
}

/**
 * A site as it is when the provisioning chain reaches the www redirect step. CreateSiteAction
 * gives the primary record the site's own www/wildcard settings up front, so the two agree
 * and a fix may read either of them.
 */
function wwwRedirectProvisionedSite(WwwRedirectType $type, bool $wildcard = false): Site
{
    $site = Site::factory()->laravel()->create([
        'domain' => 'example.com',
        'www_redirect_type' => $type,
        'allow_wildcard' => $wildcard,
    ]);

    DomainRecord::factory()->for($site)->primary()->pending()->create([
        'name' => $site->domain,
        'www_redirect_type' => $site->www_redirect_type,
        'allow_wildcard' => $site->allow_wildcard,
    ]);

    return $site;
}

function wwwRedirectProvisioningScript(Site $site): string
{
    $job = new ConfigureWwwRedirectJob($site);

    return (new ReflectionClass($job))->getMethod('generateScript')->invoke($job);
}

/**
 * Rendered through the job, the only caller of the domain update view, so the test does not
 * hard-code the variable set the view is handed.
 */
function wwwRedirectDomainUpdateScript(DomainRecord $record): string
{
    $job = new UpdateDomainJob($record);

    return (new ReflectionClass($job))->getMethod('generateScript')->invoke($job);
}

/**
 * Domain record ids of every UpdateDomainJob the fake bus recorded. A Bus::chain() records
 * only its head job; the rest wait serialized in ->chained, so those are unpacked as well.
 *
 * @return array<int, int>
 */
function wwwRedirectRegeneratedRecordIds(): array
{
    $ids = [];

    foreach (Bus::dispatched(UpdateDomainJob::class) as $job) {
        $ids[] = $job->domainRecord->id;

        foreach ($job->chained as $serialized) {
            $chained = unserialize($serialized);

            if ($chained instanceof UpdateDomainJob) {
                $ids[] = $chained->domainRecord->id;
            }
        }
    }

    return $ids;
}

function wwwRedirectEnableSsl(Certificate $certificate): void
{
    $job = new EnableSslJob($certificate);

    (new ReflectionClass($job))
        ->getMethod('handleSuccess')
        ->invoke($job, new ExecutionResult('SSL enabled', 0, 1.0));
}

// ---------------------------------------------------------------------------
// Provisioning step 3: ConfigureWwwRedirectJob
// ---------------------------------------------------------------------------

it('writes the provisioning www redirect at domain level, not into the shared site-level include', function () {
    $site = wwwRedirectProvisionedSite(WwwRedirectType::FromWww);

    // The Domain path owns the www redirect at domain level. A second, port-80-only copy in
    // the site-level include (shared by every domain of the site) conflicts with the
    // regenerated one, and nginx reports the www name as a conflicting server name.
    expect(wwwRedirectConfWrites(wwwRedirectProvisioningScript($site)))
        ->toBe(['/etc/nginx/netipar-conf/example.com/example.com/before/redirect.conf']);
});

it('redirects the provisioning www name straight to https instead of echoing the request scheme', function () {
    $site = wwwRedirectProvisionedSite(WwwRedirectType::FromWww);

    preg_match_all('/return 301 (\S+);/', wwwRedirectProvisioningScript($site), $targets);

    // `$scheme://` sends a plain-http visitor to http://<apex> instead of straight to https.
    expect(array_values(array_unique($targets[1])))->toBe(['https://example.com$request_uri']);
});

it('serves the provisioning www redirect over plain http only and keeps the acme challenge reachable', function () {
    $site = wwwRedirectProvisionedSite(WwwRedirectType::FromWww);

    $script = wwwRedirectProvisioningScript($site);

    // Without the challenge location the redirect swallows validation and the www name can
    // never be renewed (houg.hu nearly expired that way).
    expect($script)
        ->toContain('/.well-known/acme-challenge/')
        ->toContain('server_name www.example.com;')
        // No certificate exists yet, and a 443 block without one makes nginx refuse to start.
        ->not->toContain('listen 443')
        ->not->toContain(':443');
});

it('writes no provisioning www redirect for a site that wants none', function () {
    $site = wwwRedirectProvisionedSite(WwwRedirectType::None);

    $script = wwwRedirectProvisioningScript($site);

    // The job is dispatched for every site, so it has to honour the setting itself.
    expect(wwwRedirectConfWrites($script))->toBe([])
        ->and($script)->not->toContain('server_name www.example.com');
});

it('writes no provisioning www redirect for a wildcard site', function () {
    // A wildcard already serves www, and the site keeps from_www next to it because only the
    // domain record forces the two apart.
    $site = wwwRedirectProvisionedSite(WwwRedirectType::FromWww, wildcard: true);

    $script = wwwRedirectProvisioningScript($site);

    expect(wwwRedirectConfWrites($script))->toBe([])
        ->and($script)->not->toContain('server_name www.example.com');
});

it('writes no provisioning www redirect for a site that redirects to a primary domain', function () {
    // The shared partial only branches on from_www, so it would render to_primary like to_www
    // and point the apex at www.<apex>. A redirect to the primary domain means nothing for the
    // primary domain itself, and both Domain path jobs skip it as well.
    $site = wwwRedirectProvisionedSite(WwwRedirectType::ToPrimary);

    $script = wwwRedirectProvisioningScript($site);

    expect(wwwRedirectConfWrites($script))->toBe([])
        ->and($script)->not->toContain('return 301');
});

// ---------------------------------------------------------------------------
// SSL enable: EnableSslJob hands the covered records to the Domain path
// ---------------------------------------------------------------------------

it('regenerates the domain config of a record the new certificate covers and skips one it does not', function () {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);

    $primary = DomainRecord::factory()->for($site)->primary()->create([
        'name' => 'example.com',
        'www_redirect_type' => WwwRedirectType::FromWww,
    ]);
    $uncovered = DomainRecord::factory()->for($site)->alias()->create(['name' => 'other.example.com']);

    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', 'www.example.com'],
        'active' => true,
    ]);

    // Faked, otherwise the sync queue would run the jobs and reach for SSH.
    Bus::fake();

    wwwRedirectEnableSsl($certificate);

    // enable-ssl only sed-patches the listen and certificate lines, so www stays baked into
    // the 443 server_name. Only regenerating through UpdateDomainJob hands it to a redirect.
    Bus::assertDispatched(UpdateDomainJob::class);

    expect(wwwRedirectRegeneratedRecordIds())
        ->toContain($primary->id)
        ->not->toContain($uncovered->id);
});

it('regenerates every record a wildcard certificate covers, not just the first', function () {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);

    $primary = DomainRecord::factory()->for($site)->primary()->create(['name' => 'example.com']);
    $shop = DomainRecord::factory()->for($site)->alias()->create(['name' => 'shop.example.com']);
    $blog = DomainRecord::factory()->for($site)->alias()->create(['name' => 'blog.example.com']);
    DomainRecord::factory()->for($site)->alias()->create(['name' => 'unrelated.org']);

    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', '*.example.com'],
        'active' => true,
    ]);

    Bus::fake();

    wwwRedirectEnableSsl($certificate);

    Bus::assertDispatched(UpdateDomainJob::class);

    // Chained or dispatched one by one, each covered record is regenerated exactly once.
    expect(wwwRedirectRegeneratedRecordIds())
        ->toEqualCanonicalizing([$primary->id, $shop->id, $blog->id]);
});

it('regenerates a covered record even when it is already linked to an older certificate', function () {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);

    $older = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com'],
        'active' => true,
    ]);
    $primary = DomainRecord::factory()->for($site)->primary()->create([
        'name' => 'example.com',
        'certificate_id' => $older->id,
    ]);

    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', 'www.example.com'],
        'active' => true,
    ]);

    Bus::fake();

    wwwRedirectEnableSsl($certificate);

    // Adding www to a site that already has an apex-only certificate: linking skips records
    // that carry a certificate, but this is exactly the record whose www still has to be
    // handed to a redirect, so the regeneration cannot hang off the linking.
    Bus::assertDispatched(UpdateDomainJob::class);

    expect(wwwRedirectRegeneratedRecordIds())->toContain($primary->id);
});

it('regenerates the primary domain first, whatever order the database returns the records in', function () {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);

    // Both aliases exist before the primary and sort before it by name, so neither insertion order
    // nor the (site_id, name) index puts the primary at the head of the relation. A chain stops at
    // its first failed job, and an alias whose nginx config is missing fails the update script:
    // any order but primary-first can end the chain before the primary is regenerated.
    $zulu = DomainRecord::factory()->for($site)->alias()->create(['name' => 'zulu.example.com']);
    $alpha = DomainRecord::factory()->for($site)->alias()->create(['name' => 'alpha.example.com']);
    $primary = DomainRecord::factory()->for($site)->primary()->create(['name' => 'example.com']);

    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', '*.example.com'],
        'active' => true,
    ]);

    Bus::fake();

    wwwRedirectEnableSsl($certificate);

    // Primary first, then the rest in the order they were created: the same chain every time.
    expect(wwwRedirectRegeneratedRecordIds())->toBe([$primary->id, $zulu->id, $alpha->id]);
});

it('leaves out a covered record that has no nginx config on disk to regenerate', function (DomainRecordStatus $status) {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);

    $primary = DomainRecord::factory()->for($site)->primary()->create(['name' => 'example.com']);
    DomainRecord::factory()->for($site)->alias()->create(['name' => 'aaa.example.com', 'status' => $status]);

    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', '*.example.com'],
        'active' => true,
    ]);

    Bus::fake();

    wwwRedirectEnableSsl($certificate);

    // domain/update.blade.php exits 1 when sites-available/<domain> is missing, which is the
    // case for a record still pending, creating or failed, and the chain would stop there.
    // Disabled is not in this set: that record's config is still on disk and is regenerated.
    expect(wwwRedirectRegeneratedRecordIds())->toBe([$primary->id]);
})->with(
    collect(DomainRecordStatus::cases())
        ->reject(fn (DomainRecordStatus $status): bool => in_array(
            $status,
            [DomainRecordStatus::Enabled, DomainRecordStatus::Disabled],
            true,
        ))
        ->mapWithKeys(fn (DomainRecordStatus $status): array => [$status->value => $status])
        ->all(),
);

it('regenerates a covered record that is disabled, because its nginx config is still on disk', function () {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);

    $primary = DomainRecord::factory()->for($site)->primary()->create(['name' => 'example.com']);
    $disabled = DomainRecord::factory()->for($site)->alias()->create([
        'name' => 'aaa.example.com',
        'status' => DomainRecordStatus::Disabled,
    ]);

    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com', '*.example.com'],
        'active' => true,
    ]);

    Bus::fake();

    wwwRedirectEnableSsl($certificate);

    // Disabling a domain only drops the sites-enabled symlink and re-enabling only puts it back -
    // neither regenerates. Skipping a disabled record here would bring it back later with the www
    // name still in its SSL server_name, which is the bug this whole change exists to remove.
    expect(wwwRedirectRegeneratedRecordIds())->toBe([$primary->id, $disabled->id]);
});

it('dispatches nothing when no covered record is enabled', function () {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);

    DomainRecord::factory()->for($site)->primary()->pending()->create(['name' => 'example.com']);

    $certificate = Certificate::factory()->for($site)->create([
        'type' => CertificateType::LetsEncrypt,
        'status' => CertificateStatus::Installed,
        'domains' => ['example.com'],
        'active' => true,
    ]);

    Bus::fake();

    wwwRedirectEnableSsl($certificate);

    // An empty chain would dereference null when it dispatches its first job.
    Bus::assertNothingDispatched();
});

// ---------------------------------------------------------------------------
// Domain regeneration: UpdateDomainJob retires the stale site-level file
// ---------------------------------------------------------------------------

it('removes the stale site-level www redirect when the primary domain is regenerated', function (WwwRedirectType $type) {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);

    $primary = DomainRecord::factory()->for($site)->primary()->create([
        'name' => 'example.com',
        'www_redirect_type' => $type,
    ]);

    $removed = wwwRedirectRemovedFiles(wwwRedirectDomainUpdateScript($primary));

    // The old provisioning step ignored the setting, so sites provisioned before the fix carry
    // a from_www redirect.conf at site level whatever they chose, and the regenerated
    // domain-level file has to fight it (to_www redirects the opposite way). The removal must
    // not depend on which www redirect the primary domain ends up with.
    expect(in_array('/etc/nginx/netipar-conf/example.com/before/redirect.conf', $removed, true))
        ->toBeTrue('Expected the primary domain update to remove the site-level redirect.conf; it removes: '.implode(', ', $removed));
})->with([
    'from_www' => WwwRedirectType::FromWww,
    'to_www' => WwwRedirectType::ToWww,
    'none' => WwwRedirectType::None,
]);

it('leaves the shared site-level redirect file alone when an alias is regenerated', function () {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);

    DomainRecord::factory()->for($site)->primary()->create(['name' => 'example.com']);
    $alias = DomainRecord::factory()->for($site)->alias()->create([
        'name' => 'other.example.com',
        'www_redirect_type' => WwwRedirectType::FromWww,
    ]);

    $removed = wwwRedirectRemovedFiles(wwwRedirectDomainUpdateScript($alias));

    // Guard, green before the fix by design: the site-level include is shared by every
    // domain of the site, so only the primary domain may retire it.
    expect(in_array('/etc/nginx/netipar-conf/example.com/before/redirect.conf', $removed, true))
        ->toBeFalse('An alias update must not remove the shared site-level redirect.conf; it removes: '.implode(', ', $removed));
});

it('retires the site-level redirect on the record named after the site, whatever its type', function () {
    $site = Site::factory()->laravel()->create(['domain' => 'example.com']);

    // What DomainRecordController::markAsPrimary leaves behind: it swaps the record types and
    // never touches sites.domain, so the record named after the site is now an alias and the
    // promoted one carries a different name.
    $siteNamed = DomainRecord::factory()->for($site)->alias()->create(['name' => 'example.com']);
    $promoted = DomainRecord::factory()->for($site)->primary()->create(['name' => 'shop.example.com']);

    // The legacy file serves www.example.com, so it belongs to the record named example.com.
    // Keying the guard on the primary type would retire it from the wrong record.
    expect(wwwRedirectRemovedFiles(wwwRedirectDomainUpdateScript($siteNamed)))
        ->toContain('/etc/nginx/netipar-conf/example.com/before/redirect.conf')
        ->and(wwwRedirectRemovedFiles(wwwRedirectDomainUpdateScript($promoted)))
        ->not->toContain('/etc/nginx/netipar-conf/example.com/before/redirect.conf');
});
