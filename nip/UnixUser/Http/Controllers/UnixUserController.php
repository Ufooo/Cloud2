<?php

namespace Nip\UnixUser\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Server\Data\ServerData;
use Nip\Server\Models\Server;
use Nip\UnixUser\Actions\CreateUnixUser;
use Nip\UnixUser\Enums\UserStatus;
use Nip\UnixUser\Http\Requests\StoreUnixUserRequest;
use Nip\UnixUser\Http\Resources\UnixUserResource;
use Nip\UnixUser\Models\UnixUser;

class UnixUserController extends Controller
{
    use LoadsServerPermissions;

    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

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
        (new CreateUnixUser)->handle($server, $request->validated('username'));

        return redirect()
            ->route('servers.unix-users', $server)
            ->with('success', 'Unix user created successfully.');
    }

    public function destroy(Server $server, UnixUser $user): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($user->server_id === $server->id, 403);
        abort_if($user->username === 'root', 403, 'Cannot delete the root user.');
        abort_if($user->username === 'netipar', 403, 'Cannot delete the netipar user.');
        abort_if($user->status === UserStatus::Installing, 403, 'Cannot delete a user while installation is in progress.');

        $user->delete();

        return redirect()
            ->route('servers.unix-users', $server)
            ->with('success', 'Unix user deleted successfully.');
    }
}
