<?php

use Illuminate\Support\Facades\Queue;
use Nip\SecurityMonitor\Jobs\RunSecurityScanJob;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Site\Models\Site;

test('security scan command can scan specific site', function () {
    Queue::fake();

    $site = Site::factory()->create([
        'git_monitor_enabled' => true,
        'repository' => 'User/Repository',
    ]);

    $this->artisan('security:scan', ['--site' => $site->id])
        ->expectsOutput("Dispatched security scan for site: {$site->domain}")
        ->assertExitCode(0);

    Queue::assertPushed(RunSecurityScanJob::class);
});

test('security scan command warns when site has no monitoring enabled', function () {
    $site = Site::factory()->create([
        'git_monitor_enabled' => false,
        'repository' => 'User/Repository',
    ]);

    $this->artisan('security:scan', ['--site' => $site->id])
        ->expectsOutput("Site '{$site->domain}' has no monitoring enabled.")
        ->assertExitCode(1);
});

test('security scan command warns when site has no repository', function () {
    $site = Site::factory()->create([
        'git_monitor_enabled' => true,
        'repository' => null,
    ]);

    $this->artisan('security:scan', ['--site' => $site->id])
        ->expectsOutput("Site '{$site->domain}' has no repository configured.")
        ->assertExitCode(1);
});

test('security scan command can scan all sites on server', function () {
    Queue::fake();

    $site1 = Site::factory()->create([
        'git_monitor_enabled' => true,
        'repository' => 'User/Repository1',
    ]);
    $site2 = Site::factory()->create([
        'server_id' => $site1->server_id,
        'git_monitor_enabled' => true,
        'repository' => 'User/Repository2',
    ]);

    $this->artisan('security:scan', ['--server' => $site1->server_id])
        ->assertExitCode(0);

    Queue::assertPushed(RunSecurityScanJob::class);
});

test('security status command shows summary', function () {
    Site::factory()->count(5)->create([
        'git_monitor_enabled' => true,
    ]);

    $this->artisan('security:status')
        ->expectsOutputToContain('Security Monitoring Summary')
        ->expectsOutputToContain('Monitored Sites')
        ->assertExitCode(0);
});

test('security status command shows site details', function () {
    $site = Site::factory()->create([
        'git_monitor_enabled' => true,
        'security_scan_interval_minutes' => 60,
        'security_scan_retention_days' => 30,
    ]);

    SecurityScan::factory()->create([
        'site_id' => $site->id,
        'server_id' => $site->server_id,
        'status' => 'clean',
        'completed_at' => now(),
    ]);

    $this->artisan('security:status', ['--site' => $site->id])
        ->expectsOutputToContain($site->domain)
        ->expectsOutputToContain('Git Monitor')
        ->expectsOutputToContain('Last Scan Results')
        ->assertExitCode(0);
});

test('security cleanup command deletes old scans', function () {
    $site = Site::factory()->create([
        'security_scan_retention_days' => 7,
        'git_monitor_enabled' => true,
    ]);

    // Create old scan (should be deleted)
    SecurityScan::factory()->create([
        'site_id' => $site->id,
        'server_id' => $site->server_id,
        'created_at' => now()->subDays(10),
    ]);

    // Create recent scan (should be kept)
    SecurityScan::factory()->create([
        'site_id' => $site->id,
        'server_id' => $site->server_id,
        'created_at' => now()->subDays(3),
    ]);

    expect(SecurityScan::count())->toBe(2);

    $this->artisan('security:cleanup')
        ->assertExitCode(0);

    expect(SecurityScan::count())->toBe(1);
});
