<?php

use App\Models\User;
use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\SecurityMonitor\Models\SecurityGitChange;
use Nip\SecurityMonitor\Models\SecurityGitWhitelist;
use Nip\SecurityMonitor\Models\SecurityScan;
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

it('can create git whitelist entry', function () {
    $this->actingAs($this->user)
        ->post(route('securityMonitor.gitWhitelist.store'), [
            'site_id' => $this->site->id,
            'file_path' => 'vendor/autoload.php',
            'change_type' => GitChangeType::Modified->value,
            'reason' => 'Composer update',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'File added to whitelist.');

    $this->assertDatabaseHas('security_git_whitelists', [
        'site_id' => $this->site->id,
        'file_path' => 'vendor/autoload.php',
        'change_type' => GitChangeType::Modified->value,
        'reason' => 'Composer update',
        'created_by' => $this->user->id,
    ]);
});

it('validates git whitelist request fields', function () {
    $this->actingAs($this->user)
        ->post(route('securityMonitor.gitWhitelist.store'), [
            'site_id' => '',
            'file_path' => '',
            'change_type' => 'invalid',
        ])
        ->assertSessionHasErrors(['site_id', 'file_path', 'change_type']);
});

it('validates site_id exists in database', function () {
    $this->actingAs($this->user)
        ->post(route('securityMonitor.gitWhitelist.store'), [
            'site_id' => 99999,
            'file_path' => 'test.php',
            'change_type' => GitChangeType::Modified->value,
        ])
        ->assertSessionHasErrors(['site_id']);
});

it('accepts nullable reason field', function () {
    $this->actingAs($this->user)
        ->post(route('securityMonitor.gitWhitelist.store'), [
            'site_id' => $this->site->id,
            'file_path' => 'test.php',
            'change_type' => GitChangeType::Any->value,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('security_git_whitelists', [
        'site_id' => $this->site->id,
        'file_path' => 'test.php',
        'reason' => null,
    ]);
});

it('can delete git whitelist entry', function () {
    $whitelist = SecurityGitWhitelist::factory()->create([
        'site_id' => $this->site->id,
    ]);

    $this->actingAs($this->user)
        ->delete(route('securityMonitor.gitWhitelist.destroy', $whitelist))
        ->assertRedirect()
        ->assertSessionHas('success', 'Whitelist entry removed.');

    $this->assertDatabaseMissing('security_git_whitelists', [
        'id' => $whitelist->id,
    ]);
});

it('can whitelist all git changes from latest scan', function () {
    $scan = SecurityScan::factory()->create([
        'site_id' => $this->site->id,
    ]);

    SecurityGitChange::factory()->create([
        'scan_id' => $scan->id,
        'site_id' => $this->site->id,
        'file_path' => 'file1.php',
        'change_type' => GitChangeType::Modified,
        'is_whitelisted' => false,
    ]);

    SecurityGitChange::factory()->create([
        'scan_id' => $scan->id,
        'site_id' => $this->site->id,
        'file_path' => 'file2.php',
        'change_type' => GitChangeType::Untracked,
        'is_whitelisted' => false,
    ]);

    SecurityGitChange::factory()->create([
        'scan_id' => $scan->id,
        'site_id' => $this->site->id,
        'file_path' => 'file3.php',
        'change_type' => GitChangeType::Modified,
        'is_whitelisted' => true,
    ]);

    $this->actingAs($this->user)
        ->post(route('securityMonitor.gitWhitelist.all', $this->site))
        ->assertRedirect()
        ->assertSessionHas('success', 'Added 2 files to whitelist.');

    $this->assertDatabaseHas('security_git_whitelists', [
        'site_id' => $this->site->id,
        'file_path' => 'file1.php',
        'change_type' => GitChangeType::Modified->value,
        'reason' => 'Bulk whitelisted',
    ]);

    $this->assertDatabaseHas('security_git_whitelists', [
        'site_id' => $this->site->id,
        'file_path' => 'file2.php',
        'change_type' => GitChangeType::Untracked->value,
        'reason' => 'Bulk whitelisted',
    ]);

    $this->assertDatabaseMissing('security_git_whitelists', [
        'site_id' => $this->site->id,
        'file_path' => 'file3.php',
    ]);
});

it('returns error when trying to whitelist all with no scan', function () {
    $this->actingAs($this->user)
        ->post(route('securityMonitor.gitWhitelist.all', $this->site))
        ->assertRedirect()
        ->assertSessionHas('error', 'No scan found.');
});

it('requires authentication for all git whitelist actions', function () {
    $whitelist = SecurityGitWhitelist::factory()->create([
        'site_id' => $this->site->id,
    ]);

    $this->post(route('securityMonitor.gitWhitelist.store'), [
        'site_id' => $this->site->id,
        'file_path' => 'test.php',
        'change_type' => GitChangeType::Modified->value,
    ])->assertRedirect(route('login'));

    $this->delete(route('securityMonitor.gitWhitelist.destroy', $whitelist))
        ->assertRedirect(route('login'));

    $this->post(route('securityMonitor.gitWhitelist.all', $this->site))
        ->assertRedirect(route('login'));
});
