<?php

use Nip\SecurityMonitor\Actions\CleanupOldScans;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Site\Models\Site;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->action = new CleanupOldScans;
});

it('deletes scans older than retention period', function () {
    $site = Site::factory()->create([
        'git_monitor_enabled' => true,
        'security_scan_retention_days' => 7,
    ]);

    $oldScan = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'created_at' => now()->subDays(10),
    ]);

    $recentScan = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'created_at' => now()->subDays(3),
    ]);

    $deletedCount = $this->action->cleanupForSite($site);

    expect($deletedCount)->toBe(1)
        ->and(SecurityScan::query()->find($oldScan->id))->toBeNull()
        ->and(SecurityScan::query()->find($recentScan->id))->not->toBeNull();
});

it('uses default retention period of 7 days when using factory defaults', function () {
    $site = Site::factory()->create([
        'git_monitor_enabled' => true,
    ]);

    SecurityScan::factory()->create([
        'site_id' => $site->id,
        'created_at' => now()->subDays(10),
    ]);

    $deletedCount = $this->action->cleanupForSite($site);

    expect($deletedCount)->toBe(1);
});

it('respects custom retention period', function () {
    $site = Site::factory()->create([
        'git_monitor_enabled' => true,
        'security_scan_retention_days' => 30,
    ]);

    $scan25DaysOld = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'created_at' => now()->subDays(25),
    ]);

    $scan35DaysOld = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'created_at' => now()->subDays(35),
    ]);

    $deletedCount = $this->action->cleanupForSite($site);

    expect($deletedCount)->toBe(1)
        ->and(SecurityScan::query()->find($scan25DaysOld->id))->not->toBeNull()
        ->and(SecurityScan::query()->find($scan35DaysOld->id))->toBeNull();
});

it('does not delete scans within retention period', function () {
    $site = Site::factory()->create([
        'git_monitor_enabled' => true,
        'security_scan_retention_days' => 7,
    ]);

    SecurityScan::factory()->count(3)->create([
        'site_id' => $site->id,
        'created_at' => now()->subDays(5),
    ]);

    $deletedCount = $this->action->cleanupForSite($site);

    expect($deletedCount)->toBe(0);
});

it('cleans up scans for all sites with git monitoring enabled', function () {
    $gitEnabledSite1 = Site::factory()->create([
        'git_monitor_enabled' => true,
        'security_scan_retention_days' => 7,
    ]);

    $gitEnabledSite2 = Site::factory()->create([
        'git_monitor_enabled' => true,
        'security_scan_retention_days' => 7,
    ]);

    $disabledSite = Site::factory()->create([
        'git_monitor_enabled' => false,
    ]);

    SecurityScan::factory()->create([
        'site_id' => $gitEnabledSite1->id,
        'created_at' => now()->subDays(10),
    ]);

    SecurityScan::factory()->create([
        'site_id' => $gitEnabledSite2->id,
        'created_at' => now()->subDays(10),
    ]);

    SecurityScan::factory()->create([
        'site_id' => $disabledSite->id,
        'created_at' => now()->subDays(10),
    ]);

    $totalDeleted = $this->action->handle();

    expect($totalDeleted)->toBe(2);
});

it('returns zero when no old scans exist', function () {
    $site = Site::factory()->create([
        'git_monitor_enabled' => true,
        'security_scan_retention_days' => 7,
    ]);

    SecurityScan::factory()->create([
        'site_id' => $site->id,
        'created_at' => now(),
    ]);

    $totalDeleted = $this->action->handle();

    expect($totalDeleted)->toBe(0);
});

it('handles sites with no scans', function () {
    Site::factory()->create([
        'git_monitor_enabled' => true,
        'security_scan_retention_days' => 7,
    ]);

    $totalDeleted = $this->action->handle();

    expect($totalDeleted)->toBe(0);
});
