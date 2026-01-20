<?php

use App\Models\User;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::factory()->connected()->create();
    $this->site = Site::factory()->create([
        'server_id' => $this->server->id,
    ]);
});

it('can update security settings', function () {
    $this->actingAs($this->user)
        ->patch(route('sites.securitySettings.update', $this->site), [
            'security_scan_interval_minutes' => 60,
            'security_scan_retention_days' => 14,
            'git_monitor_enabled' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Security settings updated.');

    expect($this->site->fresh())
        ->security_scan_interval_minutes->toBe(60)
        ->security_scan_retention_days->toBe(14)
        ->git_monitor_enabled->toBeTrue();
});

it('can enable git monitoring', function () {
    $this->actingAs($this->user)
        ->patch(route('sites.securitySettings.update', $this->site), [
            'git_monitor_enabled' => true,
        ])
        ->assertRedirect();

    expect($this->site->fresh())
        ->git_monitor_enabled->toBeTrue();
});

it('can disable git monitoring', function () {
    $this->site->update(['git_monitor_enabled' => true]);

    $this->actingAs($this->user)
        ->patch(route('sites.securitySettings.update', $this->site), [
            'git_monitor_enabled' => false,
        ])
        ->assertRedirect();

    expect($this->site->fresh())
        ->git_monitor_enabled->toBeFalse();
});

it('validates scan interval minutes to allowed values', function () {
    $this->actingAs($this->user)
        ->patch(route('sites.securitySettings.update', $this->site), [
            'security_scan_interval_minutes' => 999,
        ])
        ->assertSessionHasErrors(['security_scan_interval_minutes']);
});

it('accepts valid scan interval minutes', function () {
    $validIntervals = [15, 30, 60, 120, 360, 720, 1440];

    foreach ($validIntervals as $interval) {
        $this->actingAs($this->user)
            ->patch(route('sites.securitySettings.update', $this->site), [
                'security_scan_interval_minutes' => $interval,
            ])
            ->assertRedirect();

        expect($this->site->fresh()->security_scan_interval_minutes)->toBe($interval);
    }
});

it('validates retention days to allowed values', function () {
    $this->actingAs($this->user)
        ->patch(route('sites.securitySettings.update', $this->site), [
            'security_scan_retention_days' => 999,
        ])
        ->assertSessionHasErrors(['security_scan_retention_days']);
});

it('accepts valid retention days', function () {
    $validDays = [1, 3, 7, 14, 30];

    foreach ($validDays as $days) {
        $this->actingAs($this->user)
            ->patch(route('sites.securitySettings.update', $this->site), [
                'security_scan_retention_days' => $days,
            ])
            ->assertRedirect();

        expect($this->site->fresh()->security_scan_retention_days)->toBe($days);
    }
});

it('can update only specific fields', function () {
    $this->site->update([
        'git_monitor_enabled' => false,
        'security_scan_interval_minutes' => 30,
    ]);

    $this->actingAs($this->user)
        ->patch(route('sites.securitySettings.update', $this->site), [
            'git_monitor_enabled' => true,
        ])
        ->assertRedirect();

    expect($this->site->fresh())
        ->git_monitor_enabled->toBeTrue()
        ->security_scan_interval_minutes->toBe(30);
});

it('requires authentication to update security settings', function () {
    $this->patch(route('sites.securitySettings.update', $this->site), [
        'git_monitor_enabled' => true,
    ])->assertRedirect(route('login'));
});
