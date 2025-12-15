<?php

use App\Models\User;
use Nip\Server\Models\Server;
use Nip\SshKey\Enums\SshKeyStatus;
use Nip\SshKey\Models\SshKey;
use Nip\SshKey\Models\UserSshKey;
use Nip\UnixUser\Models\UnixUser;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

it('can create server with selected user ssh keys', function () {
    $user = User::factory()->create();

    $userSshKey1 = UserSshKey::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Laptop Key',
    ]);

    $userSshKey2 = UserSshKey::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Desktop Key',
    ]);

    $serverData = [
        'name' => 'Production Server',
        'provider' => 'digitalocean',
        'type' => 'app',
        'ip_address' => '192.168.1.1',
        'ssh_port' => '22',
        'php_version' => 'php83',
        'ubuntu_version' => '24.04',
        'timezone' => 'UTC',
        'ssh_key_ids' => [$userSshKey1->id, $userSshKey2->id],
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    $response->assertRedirect();

    $server = Server::query()->where('name', 'Production Server')->first();

    expect($server)->not->toBeNull();

    // 2 keys × 2 unix users (root + netipar) = 4 SSH key records
    expect($server->sshKeys)->toHaveCount(4);

    // Get the unix users
    $rootUser = UnixUser::where('server_id', $server->id)->where('username', 'root')->first();
    $netiparUser = UnixUser::where('server_id', $server->id)->where('username', 'netipar')->first();

    expect($rootUser)->not->toBeNull();
    expect($netiparUser)->not->toBeNull();

    // SSH keys should be linked to root user
    assertDatabaseHas('ssh_keys', [
        'server_id' => $server->id,
        'unix_user_id' => $rootUser->id,
        'name' => 'My Laptop Key',
        'status' => 'pending',
    ]);

    assertDatabaseHas('ssh_keys', [
        'server_id' => $server->id,
        'unix_user_id' => $rootUser->id,
        'name' => 'My Desktop Key',
        'status' => 'pending',
    ]);

    // SSH keys should also be linked to netipar user
    assertDatabaseHas('ssh_keys', [
        'server_id' => $server->id,
        'unix_user_id' => $netiparUser->id,
        'name' => 'My Laptop Key',
        'status' => 'pending',
    ]);

    assertDatabaseHas('ssh_keys', [
        'server_id' => $server->id,
        'unix_user_id' => $netiparUser->id,
        'name' => 'My Desktop Key',
        'status' => 'pending',
    ]);
});

it('can create server without selecting any ssh keys', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'Production Server',
        'provider' => 'digitalocean',
        'type' => 'app',
        'ip_address' => '192.168.1.1',
        'ssh_port' => '22',
        'php_version' => 'php83',
        'ubuntu_version' => '24.04',
        'timezone' => 'UTC',
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    $response->assertRedirect();

    $server = Server::query()->where('name', 'Production Server')->first();

    expect($server)->not->toBeNull();
    expect($server->sshKeys)->toHaveCount(0);

    // Unix users should still be created
    $rootUser = UnixUser::where('server_id', $server->id)->where('username', 'root')->first();
    $netiparUser = UnixUser::where('server_id', $server->id)->where('username', 'netipar')->first();

    expect($rootUser)->not->toBeNull();
    expect($netiparUser)->not->toBeNull();
    expect($rootUser->status->value)->toBe('installing');
    expect($netiparUser->status->value)->toBe('installing');
});

it('validates that ssh key ids must belong to authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $otherUserSshKey = UserSshKey::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $serverData = [
        'name' => 'Production Server',
        'provider' => 'digitalocean',
        'type' => 'app',
        'ip_address' => '192.168.1.1',
        'ssh_port' => '22',
        'php_version' => 'php83',
        'ubuntu_version' => '24.04',
        'timezone' => 'UTC',
        'ssh_key_ids' => [$otherUserSshKey->id],
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    $response->assertSessionHasErrors(['ssh_key_ids.0']);
});

it('validates that ssh key ids must be integers', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'Production Server',
        'provider' => 'digitalocean',
        'type' => 'app',
        'ip_address' => '192.168.1.1',
        'ssh_port' => '22',
        'php_version' => 'php83',
        'ubuntu_version' => '24.04',
        'timezone' => 'UTC',
        'ssh_key_ids' => ['invalid', 'not-an-id'],
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($user)
        ->post(route('servers.store'), $serverData);

    $response->assertSessionHasErrors(['ssh_key_ids.0', 'ssh_key_ids.1']);
});

it('marks ssh keys and unix users as installed when provisioning completes', function () {
    $server = Server::factory()->create([
        'provision_step' => 1,
    ]);

    // Server factory automatically creates root and netipar users via configure()
    $rootUser = UnixUser::where('server_id', $server->id)->where('username', 'root')->first();
    $netiparUser = UnixUser::where('server_id', $server->id)->where('username', 'netipar')->first();

    // Update their status to Installing (simulating server creation state)
    $rootUser->update(['status' => \Nip\UnixUser\Enums\UserStatus::Installing]);
    $netiparUser->update(['status' => \Nip\UnixUser\Enums\UserStatus::Installing]);

    $sshKey1 = SshKey::factory()->create([
        'server_id' => $server->id,
        'unix_user_id' => $rootUser->id,
        'status' => SshKeyStatus::Pending,
    ]);

    $sshKey2 = SshKey::factory()->create([
        'server_id' => $server->id,
        'unix_user_id' => $netiparUser->id,
        'status' => SshKeyStatus::Pending,
    ]);

    // Callback at status 10 marks keys and users as installed
    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->post('/provisioning/callback/status', [
            'server_id' => $server->id,
            'status' => 10,
        ]);

    $response->assertSuccessful();

    $sshKey1->refresh();
    $sshKey2->refresh();
    $rootUser->refresh();
    $netiparUser->refresh();

    expect($sshKey1->status)->toBe(SshKeyStatus::Installed);
    expect($sshKey2->status)->toBe(SshKeyStatus::Installed);
    expect($rootUser->status)->toBe(\Nip\UnixUser\Enums\UserStatus::Installed);
    expect($netiparUser->status)->toBe(\Nip\UnixUser\Enums\UserStatus::Installed);

    $server->refresh();
    expect($server->status->value)->toBe('connected');
    expect($server->is_ready)->toBeTrue();
});

it('loads user ssh keys on server create page', function () {
    $user = User::factory()->create();

    UserSshKey::factory()->count(3)->create([
        'user_id' => $user->id,
    ]);

    $response = actingAs($user)
        ->get(route('servers.create'));

    $response->assertSuccessful();

    $props = $response->viewData('page')['props'];

    expect($props)->toHaveKey('userSshKeys');

    // Inertia wraps Resource::collection results - access the actual data
    $userSshKeys = $props['userSshKeys'];
    if (is_object($userSshKeys) && method_exists($userSshKeys, 'toArray')) {
        $userSshKeys = $userSshKeys->toArray(request())['data'];
    } elseif (is_array($userSshKeys) && isset($userSshKeys['data'])) {
        $userSshKeys = $userSshKeys['data'];
    }

    expect(count($userSshKeys))->toBe(3);
});

it('only loads authenticated users ssh keys on create page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    UserSshKey::factory()->count(2)->create([
        'user_id' => $user->id,
    ]);

    UserSshKey::factory()->count(3)->create([
        'user_id' => $otherUser->id,
    ]);

    // Verify correct counts in database
    expect(UserSshKey::where('user_id', $user->id)->count())->toBe(2);
    expect(UserSshKey::where('user_id', $otherUser->id)->count())->toBe(3);

    $response = actingAs($user)
        ->get(route('servers.create'));

    $response->assertSuccessful();

    $props = $response->viewData('page')['props'];

    // Inertia wraps Resource::collection results - access the actual data
    $userSshKeys = $props['userSshKeys'];
    if (is_object($userSshKeys) && method_exists($userSshKeys, 'toArray')) {
        $userSshKeys = $userSshKeys->toArray(request())['data'];
    } elseif (is_array($userSshKeys) && isset($userSshKeys['data'])) {
        $userSshKeys = $userSshKeys['data'];
    }

    expect(count($userSshKeys))->toBe(2);
});
