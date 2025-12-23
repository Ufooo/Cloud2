<?php

namespace Nip\Server\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Php\Actions\CreatePhpVersion;
use Nip\Php\Enums\PhpVersion;
use Nip\Php\Enums\PhpVersionStatus;
use Nip\Server\Actions\GenerateServerSshKey;
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
use Nip\SshKey\Actions\CreateSshKey;
use Nip\SshKey\Http\Resources\UserSshKeyResource;
use Nip\SshKey\Models\UserSshKey;
use Nip\UnixUser\Actions\CreateUnixUser;
use Nip\UnixUser\Enums\UserStatus;
use Nip\UnixUser\Models\UnixUser;

class ServerController extends Controller
{
    use LoadsServerPermissions;

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

        $this->generateServerSshKey($server);
        [$rootUser, $netiparUser] = $this->createDefaultUnixUsers($server);
        $this->createPhpVersionIfNeeded($server);
        $this->createSshKeysFromRequest($request, $server, $rootUser, $netiparUser);

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
            ->route('servers.settings', $server)
            ->with('success', 'Server settings updated successfully.');
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

    /**
     * @return array{UnixUser, UnixUser}
     */
    private function createDefaultUnixUsers(Server $server): array
    {
        $createUnixUser = new CreateUnixUser;

        return [
            $createUnixUser->handle($server, 'root', UserStatus::Installing),
            $createUnixUser->handle($server, 'netipar', UserStatus::Installing),
        ];
    }

    private function createPhpVersionIfNeeded(Server $server): void
    {
        if (! in_array($server->type, [ServerType::App, ServerType::Web, ServerType::Worker])) {
            return;
        }

        $phpVersion = $server->php_version instanceof PhpVersion
            ? $server->php_version
            : PhpVersion::tryFrom($server->php_version);

        $version = $phpVersion?->version() ?? '8.4';

        (new CreatePhpVersion)->handle(
            $server,
            $version,
            PhpVersionStatus::Installing,
            isCliDefault: true,
            isSiteDefault: true,
        );
    }

    private function createSshKeysFromRequest(
        StoreServerRequest $request,
        Server $server,
        UnixUser $rootUser,
        UnixUser $netiparUser,
    ): void {
        if (! $request->filled('ssh_key_ids')) {
            return;
        }

        $userSshKeys = UserSshKey::query()
            ->whereIn('id', $request->input('ssh_key_ids'))
            ->where('user_id', auth()->id())
            ->get();

        $createSshKey = new CreateSshKey;

        foreach ($userSshKeys as $userSshKey) {
            foreach ([$rootUser, $netiparUser] as $unixUser) {
                $createSshKey->handle(
                    $server,
                    $unixUser,
                    $userSshKey->name,
                    $userSshKey->public_key,
                    $userSshKey->fingerprint,
                );
            }
        }
    }

    private function generateServerSshKey(Server $server): void
    {
        (new GenerateServerSshKey)->handle($server);
    }
}
