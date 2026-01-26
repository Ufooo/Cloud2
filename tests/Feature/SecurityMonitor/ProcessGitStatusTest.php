<?php

use Nip\SecurityMonitor\Actions\ProcessGitStatus;
use Nip\SecurityMonitor\Enums\ScanStatus;
use Nip\SecurityMonitor\Models\SecurityGitChange;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->server = Server::factory()->connected()->create();
    $this->action = app(ProcessGitStatus::class);
});

it('filters out storage symlink paths for zero downtime sites', function () {
    $site = Site::factory()->create([
        'server_id' => $this->server->id,
        'zero_downtime' => true,
        'git_monitor_enabled' => true,
    ]);

    $scan = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'server_id' => $this->server->id,
        'status' => ScanStatus::Running,
    ]);

    // Simulate git output with storage symlink changes AND real changes
    $gitOutput = json_encode([
        'sites' => [
            [
                'path' => $site->getApplicationPath(),
                'changes' => [
                    ['status' => '??', 'type' => 'untracked', 'file' => 'storage'],
                    ['status' => ' D', 'type' => 'deleted', 'file' => 'storage/app/.gitignore'],
                    ['status' => ' D', 'type' => 'deleted', 'file' => 'storage/framework/.gitignore'],
                    ['status' => ' D', 'type' => 'deleted', 'file' => 'storage/logs/.gitignore'],
                    ['status' => ' M', 'type' => 'modified', 'file' => 'bootstrap/cache/.gitignore'],
                    ['status' => ' M', 'type' => 'modified', 'file' => 'config/app.php'], // Real change!
                ],
            ],
        ],
    ]);

    $this->action->handle($scan, $site, $gitOutput);

    // Only the real change should be recorded
    $changes = SecurityGitChange::where('scan_id', $scan->id)->get();
    expect($changes)->toHaveCount(1);
    expect($changes->first()->file_path)->toBe('config/app.php');

    // Counts should reflect only real changes
    $scan->refresh();
    expect($scan->git_modified_count)->toBe(1);
    expect($scan->git_deleted_count)->toBe(0);
    expect($scan->git_untracked_count)->toBe(0);
    expect($scan->git_new_count)->toBe(1);
});

it('does not filter storage paths for non-zero-downtime sites', function () {
    $site = Site::factory()->create([
        'server_id' => $this->server->id,
        'zero_downtime' => false,
        'git_monitor_enabled' => true,
    ]);

    $scan = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'server_id' => $this->server->id,
        'status' => ScanStatus::Running,
    ]);

    // Same git output but site is not zero-downtime
    $gitOutput = json_encode([
        'sites' => [
            [
                'path' => $site->getApplicationPath(),
                'changes' => [
                    ['status' => '??', 'type' => 'untracked', 'file' => 'storage'],
                    ['status' => ' D', 'type' => 'deleted', 'file' => 'storage/app/.gitignore'],
                    ['status' => ' M', 'type' => 'modified', 'file' => 'config/app.php'],
                ],
            ],
        ],
    ]);

    $this->action->handle($scan, $site, $gitOutput);

    // All changes should be recorded for non-ZD sites
    $changes = SecurityGitChange::where('scan_id', $scan->id)->get();
    expect($changes)->toHaveCount(3);

    $scan->refresh();
    expect($scan->git_modified_count)->toBe(1);
    expect($scan->git_deleted_count)->toBe(1);
    expect($scan->git_untracked_count)->toBe(1);
});

it('filters bootstrap/cache paths for zero downtime sites', function () {
    $site = Site::factory()->create([
        'server_id' => $this->server->id,
        'zero_downtime' => true,
        'git_monitor_enabled' => true,
    ]);

    $scan = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'server_id' => $this->server->id,
        'status' => ScanStatus::Running,
    ]);

    $gitOutput = json_encode([
        'sites' => [
            [
                'path' => $site->getApplicationPath(),
                'changes' => [
                    ['status' => ' M', 'type' => 'modified', 'file' => 'bootstrap/cache/.gitignore'],
                    ['status' => ' D', 'type' => 'deleted', 'file' => 'bootstrap/cache/packages.php'],
                    ['status' => ' M', 'type' => 'modified', 'file' => 'bootstrap/app.php'], // Real change!
                ],
            ],
        ],
    ]);

    $this->action->handle($scan, $site, $gitOutput);

    // Only bootstrap/app.php should be recorded (not bootstrap/cache/*)
    $changes = SecurityGitChange::where('scan_id', $scan->id)->get();
    expect($changes)->toHaveCount(1);
    expect($changes->first()->file_path)->toBe('bootstrap/app.php');
});

it('returns clean scan when all changes are filtered for zero downtime', function () {
    $site = Site::factory()->create([
        'server_id' => $this->server->id,
        'zero_downtime' => true,
        'git_monitor_enabled' => true,
    ]);

    $scan = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'server_id' => $this->server->id,
        'status' => ScanStatus::Running,
    ]);

    // Only symlink-related changes
    $gitOutput = json_encode([
        'sites' => [
            [
                'path' => $site->getApplicationPath(),
                'changes' => [
                    ['status' => '??', 'type' => 'untracked', 'file' => 'storage'],
                    ['status' => ' D', 'type' => 'deleted', 'file' => 'storage/app/.gitignore'],
                    ['status' => ' M', 'type' => 'modified', 'file' => 'bootstrap/cache/.gitignore'],
                ],
            ],
        ],
    ]);

    $this->action->handle($scan, $site, $gitOutput);

    // No changes should be recorded
    $changes = SecurityGitChange::where('scan_id', $scan->id)->get();
    expect($changes)->toHaveCount(0);

    // Scan should have zero counts
    $scan->refresh();
    expect($scan->git_new_count)->toBe(0);
    expect($scan->git_modified_count)->toBe(0);
});
