<?php

namespace Nip\Server\Actions;

use Nip\Server\Models\Server;
use phpseclib3\Crypt\EC;

class GenerateGitSshKey
{
    /**
     * Generate an SSH key pair for git repository access.
     *
     * @return array{public_key: string, private_key: string}
     */
    public function handle(Server $server): array
    {
        $key = EC::createKey('Ed25519');

        $publicKey = $key->getPublicKey()->toString('OpenSSH', [
            'comment' => "git@{$server->name}",
        ]);

        $privateKey = $key->toString('OpenSSH');

        $server->update([
            'git_public_key' => $publicKey,
        ]);

        return [
            'public_key' => $publicKey,
            'private_key' => $privateKey,
        ];
    }
}
