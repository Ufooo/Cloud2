<?php

namespace Nip\SshKey\Actions;

use Nip\Server\Models\Server;
use Nip\SshKey\Enums\SshKeyStatus;
use Nip\SshKey\Models\SshKey;
use Nip\UnixUser\Models\UnixUser;

class CreateSshKey
{
    public function handle(
        Server $server,
        UnixUser $unixUser,
        string $name,
        string $publicKey,
        ?string $fingerprint = null,
        SshKeyStatus $status = SshKeyStatus::Pending,
    ): SshKey {
        return SshKey::create([
            'server_id' => $server->id,
            'unix_user_id' => $unixUser->id,
            'name' => $name,
            'public_key' => $publicKey,
            'fingerprint' => $fingerprint,
            'status' => $status,
        ]);
    }
}
