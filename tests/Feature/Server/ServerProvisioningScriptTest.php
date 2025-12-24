<?php

use Nip\Server\Actions\GenerateServerSshKey;
use Nip\Server\Models\Server;
use Nip\SshKey\Models\SshKey;
use Nip\UnixUser\Models\UnixUser;

it('includes server own ssh key with netipar cloud comment', function () {
    $server = Server::factory()->create();

    (new GenerateServerSshKey)->handle($server);
    $server->refresh();

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => $server->provisioning_token,
    ]));

    $response->assertOk();
    $response->assertSee('# NETipar Cloud', escape: false);
    $response->assertSee($server->ssh_public_key, escape: false);
});

it('includes user added ssh keys with key id comment', function () {
    $server = Server::factory()->create();
    $unixUser = UnixUser::factory()->for($server)->create();

    $sshKey = SshKey::factory()->forUnixUser($unixUser)->create([
        'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITEST user@example.com',
    ]);

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => $server->provisioning_token,
    ]));

    $response->assertOk();
    $response->assertSee("# Key {$sshKey->id}");
    $response->assertSee($sshKey->public_key);
});

it('includes both server and user ssh keys in provisioning script', function () {
    $server = Server::factory()->create();

    (new GenerateServerSshKey)->handle($server);
    $server->refresh();

    $unixUser = UnixUser::factory()->for($server)->create();

    $userSshKey = SshKey::factory()->forUnixUser($unixUser)->create([
        'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIUSER user@example.com',
    ]);

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => $server->provisioning_token,
    ]));

    $response->assertOk();

    // Both keys should be present
    $response->assertSee($server->ssh_public_key);
    $response->assertSee($userSshKey->public_key);
});

it('server ssh key comes first in authorized_keys', function () {
    $server = Server::factory()->create();

    (new GenerateServerSshKey)->handle($server);
    $server->refresh();

    $unixUser = UnixUser::factory()->for($server)->create();

    $userSshKey = SshKey::factory()->forUnixUser($unixUser)->create([
        'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIUSER user@example.com',
    ]);

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => $server->provisioning_token,
    ]));

    $response->assertOk();

    $content = $response->getContent();

    // Server key should appear before user key
    $serverKeyPosition = strpos($content, $server->ssh_public_key);
    $userKeyPosition = strpos($content, $userSshKey->public_key);

    expect($serverKeyPosition)->toBeLessThan($userKeyPosition);
});

it('rejects provisioning script request with invalid token', function () {
    $server = Server::factory()->create();

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => 'invalid-token',
    ]));

    $response->assertForbidden();
});
