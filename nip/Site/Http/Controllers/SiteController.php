<?php

namespace Nip\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Php\Enums\PhpVersion;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Models\Server;
use Nip\Site\Data\SiteData;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Enums\SiteType;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Http\Requests\StoreSiteRequest;
use Nip\Site\Http\Requests\UpdateSiteRequest;
use Nip\Site\Http\Resources\SiteResource;
use Nip\Site\Models\Site;

class SiteController extends Controller
{
    public function index(): Response
    {
        $sites = Site::query()
            ->with('server')
            ->orderBy('domain')
            ->paginate(15);

        $servers = Server::query()
            ->where('status', ServerStatus::Connected)
            ->orderBy('name')
            ->get()
            ->map(fn (Server $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
            ]);

        return Inertia::render('sites/Index', [
            'sites' => SiteResource::collection($sites),
            'servers' => $servers,
            'currentServer' => null,
            'siteTypes' => SiteType::options(),
        ]);
    }

    public function create(string $type): Response
    {
        $siteType = SiteType::tryFrom($type);

        abort_if($siteType === null, 404, 'Invalid site type.');

        $servers = Server::query()
            ->where('status', ServerStatus::Connected)
            ->with(['phpVersions' => fn ($q) => $q->where('status', 'installed')->orderBy('version', 'desc')])
            ->with(['unixUsers' => fn ($q) => $q->orderBy('username')])
            ->orderBy('name')
            ->get()
            ->map(fn (Server $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'phpVersions' => $s->phpVersions->map(fn ($pv) => [
                    'value' => $pv->version,
                    'label' => PhpVersion::tryFrom($pv->version)?->label() ?? $pv->version,
                    'isDefault' => $pv->is_site_default,
                ])->values()->all(),
                'unixUsers' => $s->unixUsers->map(fn ($u) => [
                    'value' => $u->username,
                    'label' => $u->username,
                ])->values()->all(),
            ]);

        return Inertia::render('sites/Create', [
            'siteType' => [
                'value' => $siteType->value,
                'label' => $siteType->label(),
            ],
            'servers' => $servers,
            'packageManagers' => PackageManager::options(),
            'wwwRedirectTypes' => WwwRedirectType::options(),
        ]);
    }

    public function store(StoreSiteRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $server = Server::findOrFail($data['server_id']);
        Gate::authorize('update', $server);

        $siteType = SiteType::from($data['type']);

        if (! isset($data['web_directory'])) {
            $data['web_directory'] = $siteType->defaultWebDirectory();
        }

        if (! isset($data['build_command'])) {
            $data['build_command'] = $siteType->defaultBuildCommand();
        }

        // Extract installation options (not stored in DB, passed to job)
        $installOptions = [
            'install_composer' => $data['install_composer'] ?? true,
            'create_database' => $data['create_database'] ?? false,
        ];
        unset($data['install_composer'], $data['create_database']);

        $site = $server->sites()->create([
            ...$data,
            'status' => SiteStatus::Pending,
            'deploy_status' => DeployStatus::NeverDeployed,
        ]);

        // TODO: Dispatch job to install the site on the server
        // InstallSiteJob::dispatch($site, $installOptions);

        return redirect()
            ->route('sites.index')
            ->with('success', 'Site created successfully.');
    }

    public function show(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $site->load('server');

        return Inertia::render('sites/Show', [
            'site' => SiteData::fromModel($site),
        ]);
    }

    public function settings(Site $site): Response
    {
        Gate::authorize('update', $site->server);

        $site->load(['server.phpVersions' => fn ($q) => $q->where('status', 'installed')->orderBy('version', 'desc')]);

        $phpVersions = $site->server->phpVersions->map(fn ($pv) => [
            'value' => $pv->version,
            'label' => PhpVersion::tryFrom($pv->version)?->label() ?? $pv->version,
        ])->values()->all();

        return Inertia::render('sites/Settings', [
            'site' => SiteData::fromModel($site),
            'siteTypes' => SiteType::options(),
            'phpVersions' => $phpVersions,
            'colors' => IdentityColor::options(),
        ]);
    }

    public function update(UpdateSiteRequest $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $site->update($request->validated());

        return redirect()
            ->back()
            ->with('success', 'Site updated successfully.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_if(
            $site->status === SiteStatus::Installing,
            403,
            'Cannot delete a site while installation is in progress.'
        );

        $site->update(['status' => SiteStatus::Deleting]);

        // TODO: Dispatch job to delete the site on the server

        $site->delete();

        return redirect()
            ->route('sites.index')
            ->with('success', 'Site deleted successfully.');
    }

    public function deploy(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($site->status === SiteStatus::Installed, 403, 'Site must be installed to deploy.');

        $site->update(['deploy_status' => DeployStatus::Deploying]);

        // TODO: Dispatch job to deploy the site on the server

        return redirect()
            ->route('sites.index')
            ->with('success', 'Deployment started.');
    }
}
