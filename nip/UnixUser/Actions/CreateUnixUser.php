<?php

namespace Nip\UnixUser\Actions;

use Nip\Server\Models\Server;
use Nip\UnixUser\Enums\UserStatus;
use Nip\UnixUser\Models\UnixUser;

class CreateUnixUser
{
    public function handle(
        Server $server,
        string $username,
        UserStatus $status = UserStatus::Pending,
    ): UnixUser {
        return UnixUser::create([
            'server_id' => $server->id,
            'username' => $username,
            'status' => $status,
        ]);
    }
}
