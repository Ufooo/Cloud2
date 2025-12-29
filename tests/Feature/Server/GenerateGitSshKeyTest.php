<?php

use Nip\Server\Actions\GenerateGitSshKey;
use Nip\Server\Models\Server;

it('generates a valid ed25519 ssh key pair', function () {
    $server = Server::factory()->create(['name' => 'test-server']);

    $action = new GenerateGitSshKey;
    $result = $action->handle($server);

    expect($result)->toHaveKeys(['public_key', 'private_key'])
        ->and($result['public_key'])->toStartWith('ssh-ed25519 ')
        ->and($result['public_key'])->toContain('git@test-server')
        ->and($result['private_key'])->toContain('-----BEGIN OPENSSH PRIVATE KEY-----');
});

it('saves the public key to the server model', function () {
    $server = Server::factory()->create();
    expect($server->git_public_key)->toBeNull();

    $action = new GenerateGitSshKey;
    $result = $action->handle($server);

    expect($server->fresh()->git_public_key)
        ->toBe($result['public_key'])
        ->toStartWith('ssh-ed25519 ');
});

it('generates unique key pairs for each server', function () {
    $server1 = Server::factory()->create();
    $server2 = Server::factory()->create();

    $action = new GenerateGitSshKey;
    $result1 = $action->handle($server1);
    $result2 = $action->handle($server2);

    expect($result1['public_key'])->not->toBe($result2['public_key'])
        ->and($result1['private_key'])->not->toBe($result2['private_key']);
});
