<?php

namespace Nip\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Enums\DatabaseUserStatus;
use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Deployment\Http\Resources\DeploymentResource;
use Nip\Deployment\Models\Deployment;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Models\Server;
use Nip\Site\Actions\CreateSiteAction;
use Nip\Site\Data\PhpVersionOptionData;
use Nip\Site\Data\ServerSiteCreationData;
use Nip\Site\Data\SiteCreationData;
use Nip\Site\Data\SiteData;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\DetectedPackage;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Enums\SiteType;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Http\Requests\StoreSiteRequest;
use Nip\Site\Http\Requests\UpdateSiteRequest;
use Nip\Site\Http\Resources\SiteResource;
use Nip\Site\Jobs\DeleteSiteJob;
use Nip\Site\Jobs\DeploySiteJob;
use Nip\Site\Models\Site;
use Nip\Site\Services\InertiaSSRService;
use Nip\Site\Services\PackageDetectionService;
use Nip\Site\Services\SitePhpVersionService;
use Nip\SourceControl\Models\SourceControl;
use Nip\SourceControl\Services\GitHubService;

class SiteController extends Controller
{
    public function index(): Response
    {
        $search = request('search');

        $sites = Site::query()
            ->with(['server', 'sourceControl', 'primaryDomain.certificate'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('domain', 'like', "%{$search}%")
                        ->orWhere('user', 'like', "%{$search}%")
                        ->orWhere('repository', 'like', "%{$search}%")
                        ->orWhereHas('server', fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('domainRecords', fn ($dq) => $dq->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('domain')
            ->paginate(15)
            ->withQueryString();

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
            'filters' => [
                'search' => $search,
            ],
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
            ->with(['databases' => fn ($q) => $q->where('status', DatabaseStatus::Installed)->with('users')->orderBy('name')])
            ->with(['databaseUsers' => fn ($q) => $q->where('status', DatabaseUserStatus::Installed)->orderBy('username')])
            ->orderBy('name')
            ->get();

        $serverOptions = ServerSiteCreationData::fromCollection($servers);

        $sourceControls = SourceControl::query()
            ->where('user_id', auth()->id())
            ->get()
            ->map(fn (SourceControl $sc) => [
                'id' => $sc->id,
                'provider' => $sc->provider->value,
                'providerLabel' => $sc->provider->label(),
                'name' => $sc->name,
            ]);

        return Inertia::render('sites/Create', [
            'siteType' => [
                'value' => $siteType->value,
                'label' => $siteType->label(),
                'webDirectory' => $siteType->defaultWebDirectory(),
                'buildCommand' => $siteType->defaultBuildCommand(),
                'isPhpBased' => $siteType->isPhpBased(),
                'supportsZeroDowntime' => $siteType->supportsZeroDowntime(),
            ],
            'servers' => $serverOptions,
            'sourceControls' => $sourceControls,
            'packageManagers' => PackageManager::options(),
            'wwwRedirectTypes' => WwwRedirectType::options(),
        ]);
    }

    public function store(StoreSiteRequest $request, CreateSiteAction $createSite): RedirectResponse
    {
        $siteData = SiteCreationData::from($request->validated());

        $server = Server::findOrFail($siteData->server_id);
        Gate::authorize('update', $server);

        $site = $createSite->handle($siteData);

        return redirect()
            ->route('sites.show', $site)->with('success', 'Site is being installed.');
    }

    public function show(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $site->refresh();
        $site->load('server');

        $recentDeployments = Deployment::where('site_id', $site->id)
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('sites/Show', [
            'site' => SiteData::fromModel($site),
            'recentDeployments' => DeploymentResource::collection($recentDeployments),
        ]);
    }

    public function settings(Site $site): Response
    {
        Gate::authorize('update', $site->server);

        $site->load([
            'server.phpVersions' => fn ($q) => $q->where('status', 'installed')->orderBy('version', 'desc'),
            'database',
            'databaseUser',
        ]);

        return Inertia::render('sites/Settings', [
            'site' => SiteData::fromModel($site),
            'siteTypes' => SiteType::options(),
            'phpVersions' => PhpVersionOptionData::toSelectOptions($site->server->phpVersions),
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
            ->back()->with('success', $message);
    }

    public function destroy(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        // Refresh and load relationships to ensure we have latest data
        $site->refresh();
        $site->load(['database', 'databaseUser']);

        abort_if(
            $site->status === SiteStatus::Installing || $site->status === SiteStatus::Deleting,
            403,
            'Cannot delete a site while installation or deletion is in progress.'
        );

        $deleteDatabase = (bool) request('delete_database', false);
        $deleteDatabaseUser = (bool) request('delete_database_user', false);

        $site->update(['status' => SiteStatus::Deleting]);

        // Build job chain: database deletion → user deletion → site deletion
        $jobs = [];

        if ($deleteDatabase && $site->database) {
            $site->database->update(['status' => \Nip\Database\Enums\DatabaseStatus::Deleting]);
            $jobs[] = new \Nip\Database\Jobs\DeleteDatabaseJob($site->database);
        }

        if ($deleteDatabaseUser && $site->databaseUser) {
            $site->databaseUser->update(['status' => \Nip\Database\Enums\DatabaseUserStatus::Deleting]);
            $jobs[] = new \Nip\Database\Jobs\DeleteDatabaseUserJob($site->databaseUser);
        }

        $jobs[] = new DeleteSiteJob($site);

        \Illuminate\Support\Facades\Bus::chain($jobs)
            ->onQueue('provisioning')
            ->dispatch();

        return redirect()
            ->route('sites.index')->with('success', 'Site is being deleted.');
    }

    public function deploy(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($site->status === SiteStatus::Installed, 403, 'Site must be installed to deploy.');
        abort_if($site->deploy_status === DeployStatus::Deploying, 409, 'A deployment is already in progress.');

        $branch = $site->branch ?? 'main';
        $commitInfo = null;

        // Fetch latest commit info from GitHub if source control is connected
        if ($site->sourceControl && $site->repository) {
            $github = new GitHubService($site->sourceControl);
            $commitInfo = $github->getLatestCommit($site->repository, $branch);
        }

        $deployment = Deployment::create([
            'site_id' => $site->id,
            'user_id' => auth()->id(),
            'status' => DeploymentStatus::Deploying,
            'branch' => $branch,
            'commit_hash' => $commitInfo['sha'] ?? null,
            'commit_message' => $commitInfo['message'] ?? null,
            'commit_author' => $commitInfo['author'] ?? null,
            'callback_token' => bin2hex(random_bytes(32)),
            'started_at' => now(),
        ]);

        $site->update(['deploy_status' => DeployStatus::Deploying]);

        DeploySiteJob::dispatch($site, $deployment);

        return redirect()
            ->route('sites.deployments.show', [$site, $deployment])->with('success', 'Deployment started.');
    }

    public function detectPackages(Site $site, PackageDetectionService $packageDetectionService): JsonResponse
    {
        Gate::authorize('view', $site->server);

        abort_unless($site->status === SiteStatus::Installed, 403, 'Site must be installed to detect packages.');

        $packages = $packageDetectionService->detectPackages($site);
        $packageDetails = $packageDetectionService->getPackageDetails($site);

        return response()->json([
            'packages' => $packages,
            'packageDetails' => $packageDetails,
        ]);
    }

    public function enableSSR(Site $site, InertiaSSRService $ssrService): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($site->status === SiteStatus::Installed, 403, 'Site must be installed to enable SSR.');

        $detectedPackages = $site->detected_packages ?? [];
        abort_unless(
            in_array(DetectedPackage::Inertia->value, $detectedPackages, true),
            403,
            'Inertia must be detected to enable SSR.'
        );

        abort_if($ssrService->isEnabled($site), 409, 'SSR is already enabled.');

        $ssrService->enable($site);

        return redirect()
            ->back()->with('success', 'Inertia SSR is being enabled...');
    }

    public function disableSSR(Site $site, InertiaSSRService $ssrService): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($site->status === SiteStatus::Installed, 403, 'Site must be installed to disable SSR.');
        abort_unless($ssrService->isEnabled($site), 409, 'SSR is not enabled.');

        $ssrService->disable($site);

        return redirect()
            ->back()->with('success', 'Inertia SSR is being disabled...');
    }
}
