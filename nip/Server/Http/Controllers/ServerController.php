<?php

namespace Nip\Server\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Php\Enums\PhpVersion;
use Nip\Server\Actions\CreateDefaultUnixUsers;
use Nip\Server\Actions\CreatePhpVersionForServer;
use Nip\Server\Actions\CreateSshKeysFromRequest;
use Nip\Server\Actions\GenerateServerSshKey;
use Nip\Server\Actions\RefreshServerMetrics;
use Nip\Server\Data\ServerData;
use Nip\Server\Data\ServerPermissionsData;
use Nip\Server\Enums\DatabaseType;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\ServerProvider;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Enums\ServerType;
use Nip\Server\Enums\Timezone;
use Nip\Server\Enums\UbuntuVersion;
use Nip\Server\Http\Requests\StoreServerRequest;
use Nip\Server\Http\Requests\UpdateServerSettingsRequest;
use Nip\Server\Http\Resources\ServerListResource;
use Nip\Server\Models\Server;
use Nip\SshKey\Http\Resources\UserSshKeyResource;
use Nip\SshKey\Models\UserSshKey;

class ServerController extends Controller
{
    use LoadsServerPermissions;

    public function __construct(
        private GenerateServerSshKey $generateServerSshKey,
        private CreateDefaultUnixUsers $createDefaultUnixUsers,
        private CreatePhpVersionForServer $createPhpVersionForServer,
        private CreateSshKeysFromRequest $createSshKeysFromRequest,
    ) {}

    public function index(): Response
    {
        $servers = Server::query()
            ->withCount(['sites', 'backgroundProcesses', 'scheduledJobs'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('servers/Index', [
            'servers' => ServerListResource::collection($servers),
        ]);
    }

    public function create(): Response
    {
        $userSshKeys = UserSshKey::query()
            ->with('user')
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        return Inertia::render('servers/Create', [
            'providers' => ServerProvider::options(),
            'serverTypes' => ServerType::options(),
            'phpVersions' => PhpVersion::options(),
            'databaseTypes' => DatabaseType::options(),
            'ubuntuVersions' => UbuntuVersion::options(),
            'timezones' => Timezone::options(),
            'userSshKeys' => UserSshKeyResource::collection($userSshKeys)->resolve(),
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

        $this->generateServerSshKey->handle($server);
        [, $netiparUser] = $this->createDefaultUnixUsers->handle($server);
        $this->createPhpVersionForServer->handle($server);
        $this->createSshKeysFromRequest->handle($request, $server, $netiparUser);

        return redirect()
            ->route('servers.show', $server)->with('success', 'Server created successfully.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        Gate::authorize('delete', $server);

        $server->delete();

        return redirect()
            ->route('servers.index')->with('success', 'Server deleted successfully.');
    }

    public function refreshMetrics(Server $server, RefreshServerMetrics $refreshServerMetrics): JsonResponse
    {
        Gate::authorize('view', $server);

        $success = $refreshServerMetrics->handle($server);
        $server->refresh();

        return response()->json([
            'success' => $success,
            'status' => $server->status->value,
            'statusLabel' => $server->status->label(),
            'isConnected' => $server->status === ServerStatus::Connected,
            'uptimeFormatted' => $server->uptime_formatted,
            'loadAvgFormatted' => $server->load_avg_formatted,
            'cpuPercent' => $server->cpu_percent ?? 0,
            'ramTotalBytes' => $server->ram_total_bytes,
            'ramUsedBytes' => $server->ram_used_bytes,
            'ramPercent' => $server->ram_percent ?? 0,
            'diskTotalBytes' => $server->disk_total_bytes,
            'diskUsedBytes' => $server->disk_used_bytes,
            'diskPercent' => $server->disk_percent ?? 0,
            'lastMetricsAt' => $server->last_metrics_at?->toISOString(),
        ]);
    }

    public function settings(Server $server): Response
    {
        Gate::authorize('view', $server);

        $this->loadServerPermissions($server);

        return Inertia::render('servers/Settings', [
            'server' => ServerData::from($server),
            'timezones' => Timezone::options(),
            'colors' => IdentityColor::options(),
        ]);
    }

    public function update(UpdateServerSettingsRequest $request, Server $server): RedirectResponse
    {
        $server->update($request->validated());

        return redirect()
            ->route('servers.settings', $server)->with('success', 'Server settings updated successfully.');
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
}
