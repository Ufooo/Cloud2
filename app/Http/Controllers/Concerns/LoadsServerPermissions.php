<?php

namespace App\Http\Controllers\Concerns;

use Nip\Server\Data\ServerPermissionsData;
use Nip\Server\Models\Server;

trait LoadsServerPermissions
{
    protected function loadServerPermissions(Server $server): void
    {
        $user = request()->user();
        $server->can = ServerPermissionsData::from([
            'view' => $user->can('view', $server),
            'update' => $user->can('update', $server),
            'delete' => $user->can('delete', $server),
        ]);
    }
}
