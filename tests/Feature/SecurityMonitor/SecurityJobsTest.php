<?php

use Illuminate\Support\Facades\Queue;
use Nip\SecurityMonitor\Jobs\CleanupOldScansJob;
use Nip\SecurityMonitor\Jobs\RunSecurityScanJob;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

test('run security scan job can be dispatched', function () {
    Queue::fake();

    $server = Server::factory()->create();
    $site = Site::factory()->create([
        'server_id' => $server->id,
        'git_monitor_enabled' => true,
    ]);

    RunSecurityScanJob::dispatch($server->id, [$site->id]);

    Queue::assertPushed(RunSecurityScanJob::class, function ($job) use ($server, $site) {
        return $job->serverId === $server->id
            && $job->siteIds === [$site->id];
    });
});

test('run security scan job has correct configuration', function () {
    $server = Server::factory()->create();
    $site = Site::factory()->create(['server_id' => $server->id]);

    $job = new RunSecurityScanJob($server->id, [$site->id]);

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(300);
});

test('run security scan job has correct tags', function () {
    $serverId = 123;
    $job = new RunSecurityScanJob($serverId, [1, 2, 3]);

    $tags = $job->tags();

    expect($tags)->toContain('security-scan')
        ->and($tags)->toContain("server:{$serverId}");
});

test('cleanup old scans job can be dispatched', function () {
    Queue::fake();

    CleanupOldScansJob::dispatch();

    Queue::assertPushed(CleanupOldScansJob::class);
});

test('cleanup old scans job has correct configuration', function () {
    $job = new CleanupOldScansJob;

    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(600);
});

test('cleanup old scans job has correct tags', function () {
    $job = new CleanupOldScansJob;

    $tags = $job->tags();

    expect($tags)->toContain('security-cleanup');
});
