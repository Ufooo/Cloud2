<?php

namespace Nip\Server\Actions;

use Nip\Server\Models\Server;
use phpseclib3\Crypt\RSA;

class GenerateServerSshKey
{
    /**
     * Generate an SSH key pair for the server.
     *
     * @return array{public_key: string, private_key: string}
     */
    public function handle(Server $server): array
    {
        $key = RSA::createKey(4096);

        $publicKey = $key->getPublicKey()->toString('OpenSSH', [
            'comment' => "server-{$server->name}",
        ]);

        $privateKey = $key->toString('OpenSSH');

        $server->update([
            'ssh_public_key' => $publicKey,
            'ssh_private_key' => $privateKey,
        ]);

        return [
            'public_key' => $publicKey,
            'private_key' => $privateKey,
        ];
    }
}
