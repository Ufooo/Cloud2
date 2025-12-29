<?php

use Nip\Server\Actions\GenerateServerSshKey;
use Nip\Server\Models\Server;
use Nip\SshKey\Models\SshKey;
use Nip\UnixUser\Models\UnixUser;

it('includes server own ssh key with netipar cloud comment for root', function () {
    $server = Server::factory()->create();

    (new GenerateServerSshKey)->handle($server);
    $server->refresh();

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => $server->provisioning_token,
    ]));

    $response->assertOk();
    $response->assertSee('# Netipar[server]: Cloud Management Key', escape: false);
    $response->assertSee($server->ssh_public_key, escape: false);
});

it('includes user ssh key only for the unix user it belongs to', function () {
    $server = Server::factory()->create();

    // Get or create unix users
    $netiparUser = UnixUser::query()
        ->where('server_id', $server->id)
        ->where('username', 'netipar')
        ->first() ?? UnixUser::factory()->for($server)->create(['username' => 'netipar']);

    $rootUser = UnixUser::query()
        ->where('server_id', $server->id)
        ->where('username', 'root')
        ->first() ?? UnixUser::factory()->for($server)->create(['username' => 'root']);

    $netiparSshKey = SshKey::factory()->forUnixUser($netiparUser)->create([
        'public_key' => 'ssh-ed25519 NETIPAR-KEY netipar@example.com',
    ]);

    $rootSshKey = SshKey::factory()->forUnixUser($rootUser)->create([
        'public_key' => 'ssh-ed25519 ROOT-KEY root@example.com',
    ]);

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => $server->provisioning_token,
    ]));

    $response->assertOk();

    $content = $response->getContent();

    // Root section should contain root's key but not netipar's
    $rootSection = extractAuthorizedKeysSection($content, 'root');
    expect($rootSection)->toContain($rootSshKey->public_key);
    expect($rootSection)->not->toContain($netiparSshKey->public_key);

    // Netipar section should contain netipar's key but not root's
    $netiparSection = extractAuthorizedKeysSection($content, 'netipar');
    expect($netiparSection)->toContain($netiparSshKey->public_key);
    expect($netiparSection)->not->toContain($rootSshKey->public_key);
});

it('includes server ssh key for both users', function () {
    $server = Server::factory()->create();

    (new GenerateServerSshKey)->handle($server);
    $server->refresh();

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => $server->provisioning_token,
    ]));

    $response->assertOk();

    $content = $response->getContent();

    // Both sections should contain the server's own SSH key
    $rootSection = extractAuthorizedKeysSection($content, 'root');
    $netiparSection = extractAuthorizedKeysSection($content, 'netipar');

    expect($rootSection)->toContain('# Netipar[server]: Cloud Management Key');
    expect($rootSection)->toContain($server->ssh_public_key);

    expect($netiparSection)->toContain('# Netipar[server]: Cloud Management Key');
    expect($netiparSection)->toContain($server->ssh_public_key);
});

it('includes key id comment for user ssh keys', function () {
    $server = Server::factory()->create();

    $netiparUser = UnixUser::query()
        ->where('server_id', $server->id)
        ->where('username', 'netipar')
        ->first() ?? UnixUser::factory()->for($server)->create(['username' => 'netipar']);

    $sshKey = SshKey::factory()->forUnixUser($netiparUser)->create([
        'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITEST user@example.com',
    ]);

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => $server->provisioning_token,
    ]));

    $response->assertOk();
    $response->assertSee("# Netipar[{$sshKey->id}]: {$sshKey->name}");
    $response->assertSee($sshKey->public_key);
});

it('rejects provisioning script request with invalid token', function () {
    $server = Server::factory()->create();

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => 'invalid-token',
    ]));

    $response->assertForbidden();
});

it('includes git ssh key setup in provisioning script', function () {
    $server = Server::factory()->create();

    $response = $this->get(route('provisioning.script', [
        'server' => $server->id,
        'token' => $server->provisioning_token,
    ]));

    $response->assertOk();

    $content = $response->getContent();

    // Check that git SSH key setup is included
    expect($content)->toContain('Configuring Git SSH key')
        ->and($content)->toContain('/home/netipar/.ssh/id_ed25519')
        ->and($content)->toContain('Host github.com')
        ->and($content)->toContain('Host gitlab.com')
        ->and($content)->toContain('Host bitbucket.org');

    // Verify the server's git public key was saved
    $server->refresh();
    expect($server->git_public_key)->toStartWith('ssh-ed25519 ');
});

/**
 * Helper to extract the authorized_keys content for a specific user.
 */
function extractAuthorizedKeysSection(string $content, string $username): string
{
    $homeDir = $username === 'root' ? '/root' : "/home/{$username}";

    // Pattern to match: cat > {home}/.ssh/authorized_keys << EOF ... EOF
    $pattern = '/cat > '.preg_quote($homeDir, '/').'\/\.ssh\/authorized_keys << EOF\n(.*?)\nEOF/s';

    if (preg_match($pattern, $content, $matches)) {
        return $matches[1];
    }

    return '';
}
