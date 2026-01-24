<?php

namespace Nip\Server\Actions;

use Nip\Server\Http\Requests\StoreServerRequest;
use Nip\Server\Models\Server;
use Nip\SshKey\Actions\CreateSshKey;
use Nip\SshKey\Models\UserSshKey;
use Nip\UnixUser\Models\UnixUser;

class CreateSshKeysFromRequest
{
    public function __construct(
        private CreateSshKey $createSshKey,
    ) {}

    public function handle(
        StoreServerRequest $request,
        Server $server,
        UnixUser $netiparUser,
    ): void {
        if (! $request->filled('ssh_key_ids')) {
            return;
        }

        $userSshKeys = UserSshKey::query()
            ->whereIn('id', $request->input('ssh_key_ids'))
            ->where('user_id', auth()->id())
            ->get();

        foreach ($userSshKeys as $userSshKey) {
            $this->createSshKey->handle(
                $server,
                $netiparUser,
                $userSshKey->name,
                $userSshKey->public_key,
                $userSshKey->fingerprint,
            );
        }
    }
}
