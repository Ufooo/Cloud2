<?php

namespace Nip\SshKey\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Server\Data\ServerData;
use Nip\Server\Data\ServerPermissionsData;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Models\Server;
use Nip\SshKey\Http\Requests\StoreSshKeyRequest;
use Nip\SshKey\Http\Resources\SshKeyResource;
use Nip\SshKey\Models\SshKey;

class SshKeyController extends Controller
{
    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);

        $this->loadServerPermissions($server);

        $keys = $server->sshKeys()
            ->with('unixUser')
            ->orderBy('name')
            ->paginate(10);

        $unixUsers = $server->unixUsers()
            ->orderBy('username')
            ->get(['id', 'username']);

        return Inertia::render('servers/SshKeys', [
            'server' => ServerData::from($server),
            'keys' => SshKeyResource::collection($keys),
            'unixUsers' => $unixUsers,
        ]);
    }

    public function store(StoreSshKeyRequest $request, Server $server): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);

        $server->sshKeys()->create($request->validated());

        return redirect()
            ->route('servers.ssh-keys', $server)
            ->with('success', 'SSH key created successfully.');
    }

    public function destroy(Server $server, SshKey $sshKey): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);
        abort_unless($sshKey->server_id === $server->id, 403);

        $sshKey->delete();

        return redirect()
            ->route('servers.ssh-keys', $server)
            ->with('success', 'SSH key deleted successfully.');
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
