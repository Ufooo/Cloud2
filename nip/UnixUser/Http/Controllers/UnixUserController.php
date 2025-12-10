<?php

namespace Nip\UnixUser\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Server\Data\ServerData;
use Nip\Server\Data\ServerPermissionsData;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Models\Server;
use Nip\UnixUser\Enums\UserStatus;
use Nip\UnixUser\Http\Requests\StoreUnixUserRequest;
use Nip\UnixUser\Http\Resources\UnixUserResource;
use Nip\UnixUser\Models\UnixUser;

class UnixUserController extends Controller
{
    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);

        $this->loadServerPermissions($server);

        $users = $server->unixUsers()
            ->orderBy('username')
            ->paginate(10);

        return Inertia::render('servers/UnixUsers', [
            'server' => ServerData::from($server),
            'users' => UnixUserResource::collection($users),
        ]);
    }

    public function store(StoreUnixUserRequest $request, Server $server): RedirectResponse
    {
        abort_unless($server->status === ServerStatus::Connected, 403);

        $server->unixUsers()->create([
            ...$request->validated(),
            'status' => UserStatus::Pending,
        ]);

        return redirect()
            ->route('servers.unix-users', $server)
            ->with('success', 'Unix user created successfully.');
    }

    public function destroy(Server $server, UnixUser $user): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);
        abort_unless($user->server_id === $server->id, 403);
        abort_if($user->username === 'netipar', 403, 'Cannot delete the netipar user.');
        abort_if($user->status === UserStatus::Installing, 403, 'Cannot delete a user while installation is in progress.');

        $user->delete();

        return redirect()
            ->route('servers.unix-users', $server)
            ->with('success', 'Unix user deleted successfully.');
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
