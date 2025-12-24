<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Nip\Server\Models\Server;
use Nip\SshKey\Enums\SshKeyStatus;
use Nip\SshKey\Jobs\RemoveSshKeyJob;
use Nip\SshKey\Jobs\SyncSshKeyJob;
use Nip\SshKey\Models\SshKey;
use Nip\UnixUser\Models\UnixUser;

beforeEach(function () {
    Queue::fake();
});

it('dispatches SyncSshKeyJob when creating an SSH key', function () {
    $user = User::factory()->create();
    $server = Server::factory()->connected()->create();
    $unixUser = UnixUser::factory()->create(['server_id' => $server->id]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.ssh-keys.store', $server), [
            'name' => 'Test SSH Key',
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKtest123456789abcdefghijklmnopqrstuv test@example.com',
            'unix_user_id' => $unixUser->id,
        ]);

    $response->assertRedirect();

    Queue::assertPushed(SyncSshKeyJob::class, function ($job) {
        return $job->sshKey->name === 'Test SSH Key';
    });

    $this->assertDatabaseHas('ssh_keys', [
        'name' => 'Test SSH Key',
        'status' => SshKeyStatus::Pending->value,
    ]);
});

it('dispatches RemoveSshKeyJob when deleting an SSH key', function () {
    $user = User::factory()->create();
    $server = Server::factory()->connected()->create();
    $unixUser = UnixUser::factory()->create(['server_id' => $server->id]);

    $sshKey = SshKey::factory()->create([
        'server_id' => $server->id,
        'unix_user_id' => $unixUser->id,
        'name' => 'Key to Delete',
        'status' => SshKeyStatus::Installed,
    ]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->delete(route('servers.ssh-keys.destroy', [$server, $sshKey]));

    $response->assertRedirect();

    Queue::assertPushed(RemoveSshKeyJob::class, function ($job) use ($sshKey) {
        return $job->sshKey->id === $sshKey->id;
    });

    $this->assertDatabaseHas('ssh_keys', [
        'id' => $sshKey->id,
        'status' => SshKeyStatus::Deleting->value,
    ]);
});

it('sets SSH key status to pending when created', function () {
    $user = User::factory()->create();
    $server = Server::factory()->connected()->create();
    $unixUser = UnixUser::factory()->create(['server_id' => $server->id]);

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.ssh-keys.store', $server), [
            'name' => 'Pending Key',
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKtest123456789abcdefghijklmnopqrstuv test@example.com',
            'unix_user_id' => $unixUser->id,
        ]);

    $sshKey = SshKey::where('name', 'Pending Key')->first();

    expect($sshKey)->not->toBeNull();
    expect($sshKey->status)->toBe(SshKeyStatus::Pending);
});

it('SyncSshKeyJob is queued on provisioning queue', function () {
    $user = User::factory()->create();
    $server = Server::factory()->connected()->create();
    $unixUser = UnixUser::factory()->create(['server_id' => $server->id]);

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.ssh-keys.store', $server), [
            'name' => 'Queue Test Key',
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKtest123456789abcdefghijklmnopqrstuv test@example.com',
            'unix_user_id' => $unixUser->id,
        ]);

    Queue::assertPushedOn('provisioning', SyncSshKeyJob::class);
});

it('RemoveSshKeyJob is queued on provisioning queue', function () {
    $user = User::factory()->create();
    $server = Server::factory()->connected()->create();
    $unixUser = UnixUser::factory()->create(['server_id' => $server->id]);

    $sshKey = SshKey::factory()->create([
        'server_id' => $server->id,
        'unix_user_id' => $unixUser->id,
    ]);

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->delete(route('servers.ssh-keys.destroy', [$server, $sshKey]));

    Queue::assertPushedOn('provisioning', RemoveSshKeyJob::class);
});
