<?php

use Illuminate\Support\Facades\Event;
use Nip\SecurityMonitor\Events\GitChangesDetected;
use Nip\SecurityMonitor\Events\SecurityScanCompleted;
use Nip\SecurityMonitor\Models\SecurityScan;
use Nip\Site\Models\Site;

test('security scan completed event can be dispatched', function () {
    Event::fake();

    $scan = SecurityScan::factory()->create();

    SecurityScanCompleted::dispatch($scan);

    Event::assertDispatched(SecurityScanCompleted::class, function ($event) use ($scan) {
        return $event->scan->id === $scan->id;
    });
});

test('git changes detected event can be dispatched', function () {
    Event::fake();

    $site = Site::factory()->create();
    $scan = SecurityScan::factory()->create([
        'site_id' => $site->id,
        'server_id' => $site->server_id,
    ]);

    GitChangesDetected::dispatch($scan, $site, 5);

    Event::assertDispatched(GitChangesDetected::class, function ($event) use ($scan, $site) {
        return $event->scan->id === $scan->id
            && $event->site->id === $site->id
            && $event->newChangesCount === 5;
    });
});
