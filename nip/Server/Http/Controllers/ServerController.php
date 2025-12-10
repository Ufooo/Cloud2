<?php

namespace Nip\Server\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Server\Data\ServerData;
use Nip\Server\Data\ServerPermissionsData;
use Nip\Server\Enums\DatabaseType;
use Nip\Server\Enums\PhpVersion;
use Nip\Server\Enums\ServerProvider;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Enums\ServerType;
use Nip\Server\Enums\Timezone;
use Nip\Server\Enums\UbuntuVersion;
use Nip\Server\Http\Requests\StoreServerRequest;
use Nip\Server\Http\Resources\ServerListResource;
use Nip\Server\Models\Server;

class ServerController extends Controller
{
    public function index(): Response
    {
        $servers = Server::query()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('servers/Index', [
            'servers' => ServerListResource::collection($servers),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('servers/Create', [
            'providers' => ServerProvider::options(),
            'serverTypes' => ServerType::options(),
            'phpVersions' => PhpVersion::options(),
            'databaseTypes' => DatabaseType::options(),
            'ubuntuVersions' => UbuntuVersion::options(),
            'timezones' => Timezone::options(),
        ]);
    }

    public function show(Server $server): Response
    {
        Gate::authorize('view', $server);

        $this->prepareServerForResponse($server);

        return Inertia::render('servers/Show', [
            'server' => ServerData::from($server),
        ]);
    }

    public function store(StoreServerRequest $request): RedirectResponse
    {
        $server = Server::create([
            ...$request->validated(),
            'status' => ServerStatus::Provisioning,
            'provisioning_token' => Str::random(64),
        ]);

        return redirect()
            ->route('servers.show', $server)
            ->with('success', 'Server created successfully.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        Gate::authorize('delete', $server);

        $server->delete();

        return redirect()
            ->route('servers.index')
            ->with('success', 'Server deleted successfully.');
    }

    public function settings(Server $server): Response
    {
        Gate::authorize('view', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);

        $this->loadServerPermissions($server);

        return Inertia::render('servers/Settings', [
            'server' => ServerData::from($server),
        ]);
    }

    private function prepareServerForResponse(Server $server): void
    {
        $user = request()->user();
        $server->can = ServerPermissionsData::from([
            'view' => $user->can('view', $server),
            'update' => $user->can('update', $server),
            'delete' => $user->can('delete', $server),
        ]);

        if ($server->status === ServerStatus::Provisioning) {
            $server->provisioning_steps = $server->getProvisioningSteps();
        }
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
