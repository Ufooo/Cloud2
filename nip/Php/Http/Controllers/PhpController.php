<?php

namespace Nip\Php\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Php\Actions\CreatePhpVersion;
use Nip\Php\Enums\PhpVersion as PhpVersionEnum;
use Nip\Php\Enums\PhpVersionStatus;
use Nip\Php\Http\Requests\InstallPhpVersionRequest;
use Nip\Php\Http\Requests\SetDefaultPhpVersionRequest;
use Nip\Php\Http\Requests\UpdatePhpSettingsRequest;
use Nip\Php\Http\Resources\PhpSettingResource;
use Nip\Php\Http\Resources\PhpVersionResource;
use Nip\Php\Jobs\InstallPhpVersionJob;
use Nip\Php\Jobs\RemovePhpVersionJob;
use Nip\Php\Jobs\SetCliDefaultPhpVersionJob;
use Nip\Php\Jobs\UpdatePhpSettingsJob;
use Nip\Php\Models\PhpVersion;
use Nip\Server\Data\ServerData;
use Nip\Server\Models\Server;

class PhpController extends Controller
{
    use LoadsServerPermissions;

    public function __construct(
        private CreatePhpVersion $createPhpVersion,
    ) {}

    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        $this->loadServerPermissions($server);

        $phpSetting = $server->phpSetting()->first();
        $phpVersions = $server->phpVersions()->orderBy('version', 'desc')->get();

        $availableVersions = collect(PhpVersionEnum::cases())
            ->map(fn ($version) => [
                'value' => $version->version(),
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

        $phpSetting = $server->phpSetting()->updateOrCreate(
            ['server_id' => $server->id],
            $request->validated()
        );

        UpdatePhpSettingsJob::dispatch($phpSetting);

        return redirect()
            ->route('servers.php', $server)->with('success', 'PHP settings update started.');
    }

    public function installVersion(InstallPhpVersionRequest $request, Server $server): RedirectResponse
    {
        Gate::authorize('update', $server);

        $version = $request->validated('version');

        $existing = $server->phpVersions()
            ->where('version', $version)
            ->first();

        if ($existing) {
            return redirect()
                ->route('servers.php', $server)
                ->withErrors(['version' => 'This PHP version is already installed on this server.']);
        }

        $phpVersion = $this->createPhpVersion->handle($server, $version, PhpVersionStatus::Installing);

        InstallPhpVersionJob::dispatch($phpVersion);

        return redirect()
            ->route('servers.php', $server)->with('success', 'PHP version installation started. This may take a few minutes.');
    }

    public function uninstallVersion(Server $server, PhpVersion $phpVersion): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($phpVersion->server_id === $server->id, 403);

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

        RemovePhpVersionJob::dispatch($phpVersion);

        return redirect()
            ->route('servers.php', $server)->with('success', 'PHP version uninstallation started.');
    }

    public function setDefault(SetDefaultPhpVersionRequest $request, Server $server, PhpVersion $phpVersion): RedirectResponse
    {
        Gate::authorize('update', $server);

        $type = $request->validated()['type'];

        if ($type === 'cli') {
            SetCliDefaultPhpVersionJob::dispatch($phpVersion);

            return redirect()
                ->route('servers.php', $server)->with('success', 'CLI default PHP version update started.');
        }

        DB::transaction(function () use ($server, $phpVersion) {
            $server->phpVersions()->update(['is_site_default' => false]);
            $phpVersion->update(['is_site_default' => true]);
        });

        return redirect()
            ->route('servers.php', $server)->with('success', 'Site default PHP version set successfully.');
    }
}
