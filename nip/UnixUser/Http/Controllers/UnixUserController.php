<?php

namespace Nip\UnixUser\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Server\Data\ServerData;
use Nip\Server\Data\ServerPermissionsData;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Models\Server;

class UnixUserController extends Controller
{
    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);

        $this->loadServerPermissions($server);

        return Inertia::render('servers/UnixUsers', [
            'server' => ServerData::from($server),
        ]);
    }

    private function loadServerPermissions(Server $server): void
    {
        $user = request()->user();
        $server->can = ServerPermissionsData::from([
            'view' => $user->can('view', $server),
            'update' => $user->can('update', $server),
            'delete' => $user->can('delete', $server),
        ]);
    }
}
