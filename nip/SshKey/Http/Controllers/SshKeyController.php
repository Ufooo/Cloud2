<?php

namespace Nip\SshKey\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Server\Data\ServerData;
use Nip\Server\Models\Server;
use Nip\SshKey\Http\Requests\StoreSshKeyRequest;
use Nip\SshKey\Http\Resources\SshKeyResource;
use Nip\SshKey\Models\SshKey;

class SshKeyController extends Controller
{
    use LoadsServerPermissions;

    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

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

        $server->sshKeys()->create($request->validated());

        return redirect()
            ->route('servers.ssh-keys', $server)
            ->with('success', 'SSH key created successfully.');
    }

    public function destroy(Server $server, SshKey $sshKey): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($sshKey->server_id === $server->id, 403);

        $sshKey->delete();

        return redirect()
            ->route('servers.ssh-keys', $server)
            ->with('success', 'SSH key deleted successfully.');
    }
}
