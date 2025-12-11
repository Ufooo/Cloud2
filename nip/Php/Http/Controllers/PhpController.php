<?php

namespace Nip\Php\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Http\Requests\InstallPhpVersionRequest;
use Nip\Php\Http\Requests\SetDefaultPhpVersionRequest;
use Nip\Php\Http\Requests\UpdatePhpSettingsRequest;
use Nip\Php\Http\Resources\PhpSettingResource;
use Nip\Php\Http\Resources\PhpVersionResource;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Data\ServerData;
use Nip\Server\Data\ServerPermissionsData;
use Nip\Server\Enums\PhpVersion as PhpVersionEnum;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Models\Server;

class PhpController extends Controller
{
    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);

        $this->loadServerPermissions($server);

        $phpSetting = $server->phpSetting()->first();
        $phpVersions = $server->phpVersions()->orderBy('version', 'desc')->get();

        $availableVersions = collect(PhpVersionEnum::cases())
            ->map(fn ($version) => [
                'value' => $version->value,
                'label' => $version->label(),
            ])
            ->values()
            ->all();

        return Inertia::render('servers/Php', [
            'server' => ServerData::from($server),
            'phpSetting' => $phpSetting ? PhpSettingResource::make($phpSetting) : null,
            'phpVersions' => PhpVersionResource::collection($phpVersions),
            'availableVersions' => $availableVersions,
        ]);
    }

    public function updateSettings(UpdatePhpSettingsRequest $request, Server $server): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);

        $server->phpSetting()->updateOrCreate(
            ['server_id' => $server->id],
            $request->validated()
        );

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP settings updated successfully.');
    }

    public function installVersion(InstallPhpVersionRequest $request, Server $server): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);

        $version = $request->validated()['version'];

        $existing = $server->phpVersions()
            ->where('version', $version)
            ->first();

        if ($existing) {
            return redirect()
                ->route('servers.php', $server)
                ->withErrors(['version' => 'This PHP version is already installed on this server.']);
        }

        $server->phpVersions()->create([
            'version' => $version,
            'status' => PhpVersionStatus::Pending,
            'is_cli_default' => false,
            'is_site_default' => false,
        ]);

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP version installation queued successfully.');
    }

    public function uninstallVersion(Server $server, PhpVersion $phpVersion): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);

        if ($phpVersion->is_cli_default) {
            return redirect()
                ->route('servers.php', $server)
                ->withErrors(['version' => 'Cannot uninstall the CLI default PHP version.']);
        }

        if ($phpVersion->is_site_default) {
            return redirect()
                ->route('servers.php', $server)
                ->withErrors(['version' => 'Cannot uninstall the site default PHP version.']);
        }

        $phpVersion->update(['status' => PhpVersionStatus::Uninstalling]);

        return redirect()
            ->route('servers.php', $server)
            ->with('success', 'PHP version uninstallation queued successfully.');
    }

    public function setDefault(SetDefaultPhpVersionRequest $request, Server $server, PhpVersion $phpVersion): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($server->status === ServerStatus::Connected, 403);

        $type = $request->validated()['type'];

        DB::transaction(function () use ($server, $phpVersion, $type) {
            if ($type === 'cli') {
                $server->phpVersions()->update(['is_cli_default' => false]);
                $phpVersion->update(['is_cli_default' => true]);
            } else {
                $server->phpVersions()->update(['is_site_default' => false]);
                $phpVersion->update(['is_site_default' => true]);
            }
        });

        $message = $type === 'cli'
            ? 'CLI default PHP version set successfully.'
            : 'Site default PHP version set successfully.';

        return redirect()
            ->route('servers.php', $server)
            ->with('success', $message);
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
