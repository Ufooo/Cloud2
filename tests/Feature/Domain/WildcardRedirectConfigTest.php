<?php

use Nip\Domain\Enums\WildcardBehavior;
use Nip\Domain\Models\Certificate;
use Nip\Site\Enums\SiteType;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

function renderWildcardDomainConfig(
    Site $site,
    string $domain,
    WildcardBehavior $behavior,
    ?Certificate $certificate,
    bool $allowWildcard = true,
): string {
    return view('provisioning.scripts.domain.partials.nginx-config', [
        'site' => $site,
        'domain' => $domain,
        'applicationPath' => '/home/deploy/'.$domain,
        'documentRoot' => '/home/deploy/'.$domain.'/public',
        'phpSocket' => '/var/run/php/php8.4-fpm-deploy.sock',
        'siteType' => SiteType::Laravel,
        'allowWildcard' => $allowWildcard,
        'wwwRedirectType' => WwwRedirectType::None,
        'wildcardBehavior' => $behavior,
        'primaryDomain' => $domain,
        'certificate' => $certificate,
    ])->render();
}

function wildcardCertificateFor(Site $site, string $domain): Certificate
{
    return Certificate::factory()->for($site)->create([
        'domains' => ['*.'.$domain, $domain],
        'path' => '/etc/nginx/ssl/'.$domain,
    ]);
}

it('sends unconfigured subdomains to the main domain', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);
    $certificate = wildcardCertificateFor($site, 'example.com');

    $config = renderWildcardDomainConfig($site, 'example.com', WildcardBehavior::Redirect, $certificate);

    // The application block keeps the apex only, so nginx can prefer any
    // subdomain that has a server block of its own.
    expect($config)
        ->toContain('server_name example.com;')
        ->not->toContain('server_name example.com *.example.com;');

    // Everything left over is caught by the wildcard block and redirected.
    expect($config)
        ->toContain('server_name *.example.com;')
        ->toContain('return 301 https://example.com$request_uri;');
});

it('never tests the host to decide the wildcard redirect', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);
    $certificate = wildcardCertificateFor($site, 'example.com');

    $config = renderWildcardDomainConfig($site, 'example.com', WildcardBehavior::Redirect, $certificate);

    // `if ($host != example.com)` runs inside an already-selected server block,
    // so it cannot know that a real subdomain has its own block. That is what
    // took down every ujbuda.thorgym.hu style subdomain. nginx resolves this by
    // server_name precedence instead: exact beats wildcard.
    expect($config)->not->toContain('if ($host');
});

it('emits a single wildcard redirect block on 443 only', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);
    $certificate = wildcardCertificateFor($site, 'example.com');

    $config = renderWildcardDomainConfig($site, 'example.com', WildcardBehavior::Redirect, $certificate);

    // Port 80 for *.example.com is already claimed by the site's ssl_redirect
    // config. A second port 80 block would be a conflicting server name and one
    // of the two would be silently ignored.
    expect(substr_count($config, 'server_name *.example.com;'))->toBe(1);
});

it('keeps serving the wildcard while no certificate covers it', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $config = renderWildcardDomainConfig($site, 'example.com', WildcardBehavior::Redirect, null);

    // Narrowing the application block before the redirect block can exist would
    // leave every subdomain homeless, falling through to the catch-all.
    expect($config)
        ->toContain('server_name example.com *.example.com;')
        ->not->toContain('server_name *.example.com;');
});

it('leaves an app served wildcard untouched', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);
    $certificate = wildcardCertificateFor($site, 'example.com');

    $config = renderWildcardDomainConfig($site, 'example.com', WildcardBehavior::Serve, $certificate);

    // thorgym.hu answers ujbuda.thorgym.hu from the application itself.
    expect($config)
        ->toContain('server_name example.com *.example.com;')
        ->not->toContain('return 301 https://example.com$request_uri;');
});

it('ignores the behaviour entirely when the domain is not wildcard', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);
    $certificate = wildcardCertificateFor($site, 'example.com');

    $config = renderWildcardDomainConfig(
        $site,
        'example.com',
        WildcardBehavior::Redirect,
        $certificate,
        allowWildcard: false,
    );

    expect($config)->not->toContain('server_name *.example.com;');
});

it('defaults to serving subdomains from the site', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    $record = $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => \Nip\Domain\Enums\DomainRecordType::Primary,
        'status' => \Nip\Domain\Enums\DomainRecordStatus::Creating,
        'allow_wildcard' => true,
    ]);

    expect($record->fresh()->wildcard_behavior)->toBe(WildcardBehavior::Serve);
});

it('stores the wildcard behaviour from the create form', function () {
    Illuminate\Support\Facades\Queue::fake();

    $user = App\Models\User::factory()->create();
    $server = Nip\Server\Models\Server::factory()->create();
    $site = Site::factory()->for($server)->create(['domain' => 'example.com']);

    $this->actingAs($user)
        ->post("/sites/{$site->slug}/domains", [
            'name' => 'alias.example.com',
            'type' => \Nip\Domain\Enums\DomainRecordType::Alias->value,
            'allow_wildcard' => true,
            'wildcard_behavior' => WildcardBehavior::Redirect->value,
        ])
        ->assertRedirect();

    $record = $site->domainRecords()->where('name', 'alias.example.com')->first();

    expect($record->wildcard_behavior)->toBe(WildcardBehavior::Redirect);
});

it('updates the wildcard behaviour of an existing domain', function () {
    Illuminate\Support\Facades\Queue::fake();

    $user = App\Models\User::factory()->create();
    $server = Nip\Server\Models\Server::factory()->create();
    $site = Site::factory()->for($server)->create(['domain' => 'example.com']);

    $record = $site->domainRecords()->create([
        'name' => 'example.com',
        'type' => \Nip\Domain\Enums\DomainRecordType::Primary,
        'status' => \Nip\Domain\Enums\DomainRecordStatus::Enabled,
        'allow_wildcard' => true,
    ]);

    $this->actingAs($user)
        ->patch("/sites/{$site->slug}/domains/{$record->id}", [
            'allow_wildcard' => true,
            'wildcard_behavior' => WildcardBehavior::Redirect->value,
        ])
        ->assertRedirect();

    expect($record->fresh()->wildcard_behavior)->toBe(WildcardBehavior::Redirect);

    Illuminate\Support\Facades\Queue::assertPushed(\Nip\Domain\Jobs\UpdateDomainJob::class);
});

it('rejects an unknown wildcard behaviour', function () {
    Illuminate\Support\Facades\Queue::fake();

    $user = App\Models\User::factory()->create();
    $server = Nip\Server\Models\Server::factory()->create();
    $site = Site::factory()->for($server)->create(['domain' => 'example.com']);

    $this->actingAs($user)
        ->post("/sites/{$site->slug}/domains", [
            'name' => 'alias.example.com',
            'type' => \Nip\Domain\Enums\DomainRecordType::Alias->value,
            'allow_wildcard' => true,
            'wildcard_behavior' => 'redirect_everything_everywhere',
        ])
        ->assertSessionHasErrors('wildcard_behavior');
});
