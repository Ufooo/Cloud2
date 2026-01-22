<?php

use App\Models\User;
use Nip\SecurityMonitor\Enums\ScanStatus;
use Nip\SecurityMonitor\Jobs\RunSecurityScanJob;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::factory()->connected()->create();
});

it('can view security monitor index page', function () {
    $this->actingAs($this->user)
        ->get(route('securityMonitor.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('security-monitor/index/page')
            ->has('serverGroups')
            ->has('summary')
        );
});

it('shows summary statistics on index page', function () {
    $site1 = Site::factory()->create([
        'server_id' => $this->server->id,
        'git_monitor_enabled' => true,
    ]);

    $site2 = Site::factory()->create([
        'server_id' => $this->server->id,
        'git_monitor_enabled' => true,
    ]);

    SecurityScan::factory()->create([
        'site_id' => $site1->id,
        'status' => ScanStatus::IssuesDetected,
        'git_new_count' => 5,
    ]);

    SecurityScan::factory()->create([
        'site_id' => $site2->id,
        'status' => ScanStatus::Clean,
    ]);

    $this->actingAs($this->user)
        ->get(route('securityMonitor.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.totalSites', 2)
            ->where('summary.gitIssuesSites', 1)
            ->where('summary.cleanSites', 1)
        );
});

it('only shows sites with issues on index', function () {
    // Site with issues - should appear
    $siteWithIssues = Site::factory()->create([
        'server_id' => $this->server->id,
        'git_monitor_enabled' => true,
    ]);

    SecurityScan::factory()->create([
        'site_id' => $siteWithIssues->id,
        'status' => ScanStatus::IssuesDetected,
        'git_new_count' => 3,
    ]);

    // Site that is clean - should NOT appear
    $cleanSite = Site::factory()->create([
        'server_id' => $this->server->id,
        'git_monitor_enabled' => true,
    ]);

    SecurityScan::factory()->create([
        'site_id' => $cleanSite->id,
        'status' => ScanStatus::Clean,
        'git_new_count' => 0,
    ]);

    // Site without monitoring - should NOT appear
    Site::factory()->create([
        'server_id' => $this->server->id,
        'git_monitor_enabled' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('securityMonitor.index'))
        ->assertOk();

    $serverGroups = $response->viewData('page')['props']['serverGroups'];

    // Resource collection may wrap in 'data' key
    $groups = is_array($serverGroups) && isset($serverGroups['data'])
        ? $serverGroups['data']
        : (is_array($serverGroups) ? $serverGroups : $serverGroups->toArray(request()));

    // Only the site with issues should appear
    expect(count($groups))->toBe(1);
    expect(count($groups[0]['sites']))->toBe(1);
    expect($groups[0]['sites'][0]['id'])->toBe($siteWithIssues->id);
});

it('can view security show page for specific site', function () {
    $site = Site::factory()->create([
        'server_id' => $this->server->id,
        'git_monitor_enabled' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('sites.securityMonitor', $site))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('security-monitor/show/page')
            ->has('site')
            ->has('lastScan')
            ->has('gitChanges')
            ->has('securitySettings')
        );
});

it('can trigger manual security scan', function () {
    Queue::fake();

    $site = Site::factory()->create([
        'server_id' => $this->server->id,
        'git_monitor_enabled' => true,
    ]);

    $this->actingAs($this->user)
        ->post(route('securityMonitor.scan', $site))
        ->assertRedirect()
        ->assertSessionHas('success', 'Security scan started.');

    Queue::assertPushed(RunSecurityScanJob::class, function ($job) use ($site) {
        return $job->serverId === $this->server->id
            && in_array($site->id, $job->siteIds);
    });
});

it('can view scan history for site', function () {
    $site = Site::factory()->create([
        'server_id' => $this->server->id,
    ]);

    SecurityScan::factory()->count(5)->create([
        'site_id' => $site->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('securityMonitor.history', $site))
        ->assertOk()
        ->json();

    expect($response)->toHaveCount(5);
});

it('limits scan history to 20 most recent scans', function () {
    $site = Site::factory()->create([
        'server_id' => $this->server->id,
    ]);

    SecurityScan::factory()->count(25)->create([
        'site_id' => $site->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('securityMonitor.history', $site))
        ->assertOk()
        ->json();

    expect($response)->toHaveCount(20);
});

it('returns scans in descending order by created_at', function () {
    $site = Site::factory()->create([
        'server_id' => $this->server->id,
    ]);

    $oldScan = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'created_at' => now()->subHours(2),
    ]);

    $newScan = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('securityMonitor.history', $site))
        ->assertOk()
        ->json();

    expect($response[0]['id'])->toBe($newScan->id)
        ->and($response[1]['id'])->toBe($oldScan->id);
});

it('requires authentication for all actions', function () {
    $site = Site::factory()->create([
        'server_id' => $this->server->id,
    ]);

    $this->get(route('securityMonitor.index'))
        ->assertRedirect(route('login'));

    $this->get(route('sites.securityMonitor', $site))
        ->assertRedirect(route('login'));

    $this->post(route('securityMonitor.scan', $site))
        ->assertRedirect(route('login'));

    $this->get(route('securityMonitor.history', $site))
        ->assertRedirect(route('login'));
});
