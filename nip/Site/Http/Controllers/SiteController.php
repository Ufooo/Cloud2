<?php

namespace Nip\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
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
use Nip\Site\Jobs\DeleteSiteJob;
use Nip\Site\Jobs\DeploySiteJob;
use Nip\Site\Jobs\InstallSiteJob;
use Nip\Site\Models\Site;
use Nip\Site\Services\SitePhpVersionService;

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

        if (! isset($data['deploy_script'])) {
            $data['deploy_script'] = $siteType->defaultDeployScript();
        }

        // Extract installation options (not stored in DB, passed to job)
        $installOptions = [
            'install_composer' => $data['install_composer'] ?? true,
            'create_database' => $data['create_database'] ?? false,
        ];
        unset($data['install_composer'], $data['create_database']);

        $site = $server->sites()->create([
            ...$data,
            'status' => SiteStatus::Installing,
            'deploy_status' => DeployStatus::NeverDeployed,
            'deploy_hook_token' => bin2hex(random_bytes(32)),
        ]);

        // Create primary domain record
        $site->domainRecords()->create([
            'name' => $site->domain,
            'type' => DomainRecordType::Primary,
            'status' => DomainRecordStatus::Pending,
            'www_redirect_type' => $site->www_redirect_type,
            'allow_wildcard' => $site->allow_wildcard,
        ]);

        InstallSiteJob::dispatch($site);

        return redirect()
            ->route('sites.show', $site)
            ->with('success', 'Site is being installed.');
    }

    public function show(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $site->refresh();
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

    public function update(UpdateSiteRequest $request, Site $site, SitePhpVersionService $phpVersionService): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $validated = $request->validated();

        // Check if PHP version is changing and site is installed
        $newPhpVersion = $validated['php_version'] ?? null;
        $currentPhpVersion = $site->php_version;
        $phpVersionChanging = $newPhpVersion !== null
            && $newPhpVersion !== $currentPhpVersion
            && $site->status === SiteStatus::Installed;

        if ($phpVersionChanging) {
            // Remove php_version from validated data - it will be updated by the job
            unset($validated['php_version']);

            // Dispatch job chain to update PHP version on the server
            $phpVersionService->updatePhpVersion($site, $newPhpVersion);
        }

        // Update other fields
        if (! empty($validated)) {
            $site->update($validated);
        }

        $message = $phpVersionChanging
            ? 'Site settings updated. PHP version change is being applied...'
            : 'Site updated successfully.';

        return redirect()
            ->back()
            ->with('success', $message);
    }

    public function destroy(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_if(
            $site->status === SiteStatus::Installing || $site->status === SiteStatus::Deleting,
            403,
            'Cannot delete a site while installation or deletion is in progress.'
        );

        $site->update(['status' => SiteStatus::Deleting]);

        DeleteSiteJob::dispatch($site);

        return redirect()
            ->route('sites.index')
            ->with('success', 'Site is being deleted.');
    }

    public function deploy(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($site->status === SiteStatus::Installed, 403, 'Site must be installed to deploy.');

        $site->update(['deploy_status' => DeployStatus::Deploying]);

        DeploySiteJob::dispatch($site);

        return redirect()
            ->route('sites.show', $site)
            ->with('success', 'Deployment started.');
    }
}
