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
use Nip\Site\Models\Site;
use Nip\UnixUser\Actions\CreateUnixUser;
use Nip\UnixUser\Enums\UserStatus;
use Nip\UnixUser\Http\Requests\StoreUnixUserRequest;
use Nip\UnixUser\Http\Resources\UnixUserResource;
use Nip\UnixUser\Jobs\CreateUnixUserJob;
use Nip\UnixUser\Jobs\RemoveUnixUserJob;
use Nip\UnixUser\Models\UnixUser;

class UnixUserController extends Controller
{
    use LoadsServerPermissions;

    public function __construct(
        private CreateUnixUser $createUnixUser,
    ) {}

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
        $unixUser = $this->createUnixUser->handle(
            $server,
            $request->validated('username'),
            UserStatus::Installing,
        );

        CreateUnixUserJob::dispatch($unixUser);

        return redirect()
            ->route('servers.unix-users', $server)->with('success', 'Unix user creation started.');
    }

    public function destroy(Server $server, UnixUser $user): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($user->server_id === $server->id, 403);
        abort_if($user->username === 'root', 403, 'Cannot delete the root user.');
        abort_if($user->username === 'netipar', 403, 'Cannot delete the netipar user.');
        abort_if($user->status === UserStatus::Installing, 403, 'Cannot delete a user while installation is in progress.');
        abort_if($user->status === UserStatus::Deleting, 403, 'User deletion is already in progress.');

        $hasSites = Site::query()
            ->where('server_id', $server->id)
            ->where('user', $user->username)
            ->exists();

        abort_if($hasSites, 403, 'Cannot delete user. Sites are using this user. Delete the sites first.');

        $user->update(['status' => UserStatus::Deleting]);

        RemoveUnixUserJob::dispatch($user);

        return redirect()
            ->route('servers.unix-users', $server)->with('success', 'Unix user deletion started.');
    }
}
