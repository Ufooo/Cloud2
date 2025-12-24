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
use Nip\SshKey\Actions\CreateSshKey;
use Nip\SshKey\Enums\SshKeyStatus;
use Nip\SshKey\Http\Requests\StoreSshKeyRequest;
use Nip\SshKey\Http\Resources\SshKeyResource;
use Nip\SshKey\Jobs\RemoveSshKeyJob;
use Nip\SshKey\Jobs\SyncSshKeyJob;
use Nip\SshKey\Models\SshKey;
use Nip\UnixUser\Models\UnixUser;

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

        $unixUser = UnixUser::findOrFail($request->validated('unix_user_id'));

        $sshKey = (new CreateSshKey)->handle(
            $server,
            $unixUser,
            $request->validated('name'),
            $request->validated('public_key'),
        );

        SyncSshKeyJob::dispatch($sshKey);

        return redirect()
            ->route('servers.ssh-keys', $server)
            ->with('success', 'SSH key created. Deploying to server...');
    }

    public function destroy(Server $server, SshKey $sshKey): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($sshKey->server_id === $server->id, 403);

        $sshKey->update(['status' => SshKeyStatus::Deleting]);

        RemoveSshKeyJob::dispatch($sshKey);

        return redirect()
            ->route('servers.ssh-keys', $server)
            ->with('success', 'Removing SSH key from server...');
    }
}
