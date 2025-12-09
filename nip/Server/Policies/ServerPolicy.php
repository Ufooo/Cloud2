<?php

namespace Nip\Server\Policies;

use App\Models\User;
use Nip\Server\Models\Server;

class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Server $server): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Server $server): bool
    {
        return true;
    }

    public function delete(User $user, Server $server): bool
    {
        return true;
    }
}
