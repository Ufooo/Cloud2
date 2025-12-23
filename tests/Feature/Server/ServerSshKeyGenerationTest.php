<?php

use App\Models\User;
use Nip\Server\Actions\GenerateServerSshKey;
use Nip\Server\Models\Server;

it('generates ssh key pair when creating a server', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'My Test Server',
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

    $server = Server::query()->where('name', 'My Test Server')->first();

    expect($server)->not->toBeNull();
    expect($server->ssh_public_key)->not->toBeNull();
    expect($server->ssh_private_key)->not->toBeNull();

    // Verify public key format (OpenSSH format starts with ssh-rsa)
    expect($server->ssh_public_key)->toStartWith('ssh-rsa ');
    expect($server->ssh_public_key)->toContain('server-My Test Server');

    // Verify private key format (OpenSSH format)
    expect($server->ssh_private_key)->toStartWith('-----BEGIN OPENSSH PRIVATE KEY-----');
    expect($server->ssh_private_key)->toContain('-----END OPENSSH PRIVATE KEY-----');
});

it('server ssh key is not linked to unix users', function () {
    $user = User::factory()->create();

    $serverData = [
        'name' => 'SSH Key Test Server',
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

    $server = Server::query()->where('name', 'SSH Key Test Server')->first();

    // Server should have its own SSH key
    expect($server->ssh_public_key)->not->toBeNull();
    expect($server->ssh_private_key)->not->toBeNull();

    // But it should NOT be linked to unix users (it's internal system key)
    expect($server->sshKeys)->toHaveCount(0);
});

it('generates unique ssh keys for each server', function () {
    $server1 = Server::factory()->create(['name' => 'Server 1']);
    $server2 = Server::factory()->create(['name' => 'Server 2']);

    (new GenerateServerSshKey)->handle($server1);
    (new GenerateServerSshKey)->handle($server2);

    $server1->refresh();
    $server2->refresh();

    expect($server1->ssh_public_key)->not->toBeNull();
    expect($server2->ssh_public_key)->not->toBeNull();

    // Keys should be different
    expect($server1->ssh_public_key)->not->toBe($server2->ssh_public_key);
    expect($server1->ssh_private_key)->not->toBe($server2->ssh_private_key);
});

it('private key is hidden from serialization', function () {
    $server = Server::factory()->create();

    (new GenerateServerSshKey)->handle($server);

    $server->refresh();

    // When serialized to array, private key should be hidden
    $serverArray = $server->toArray();

    expect($serverArray)->not->toHaveKey('ssh_private_key');
    expect($serverArray)->toHaveKey('ssh_public_key');
});
