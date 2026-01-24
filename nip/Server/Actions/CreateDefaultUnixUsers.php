<?php

namespace Nip\Server\Actions;

use Nip\Server\Models\Server;
use Nip\UnixUser\Actions\CreateUnixUser;
use Nip\UnixUser\Enums\UserStatus;
use Nip\UnixUser\Models\UnixUser;

class CreateDefaultUnixUsers
{
    public function __construct(
        private CreateUnixUser $createUnixUser,
    ) {}

    /**
     * @return array{UnixUser, UnixUser}
     */
    public function handle(Server $server): array
    {
        return [
            $this->createUnixUser->handle($server, 'root', UserStatus::Installing),
            $this->createUnixUser->handle($server, 'netipar', UserStatus::Installing),
        ];
    }
}
