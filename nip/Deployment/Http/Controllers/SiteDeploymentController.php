<?php

namespace Nip\Deployment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Deployment\Http\Requests\UpdateDeploymentSettingsRequest;
use Nip\Deployment\Http\Resources\DeploymentResource;
use Nip\Deployment\Models\Deployment;
use Nip\Site\Data\SiteData;
use Nip\Site\Models\Site;

class SiteDeploymentController extends Controller
{
    public function index(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $site->load('server');

        $deployments = Deployment::query()
            ->where('site_id', $site->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('sites/deployments/Index', [
            'site' => SiteData::fromModel($site),
            'deployments' => DeploymentResource::collection($deployments),
        ]);
    }

    public function settings(Site $site): Response
    {
        Gate::authorize('update', $site->server);

        $site->load('server');

        if (! $site->deploy_hook_token) {
            $site->regenerateDeployHookToken();
            $site->refresh();
        }

        return Inertia::render('sites/deployments/Settings', [
            'site' => SiteData::fromModel($site),
        ]);
    }

    public function updateSettings(UpdateDeploymentSettingsRequest $request, Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $site->update($request->validated());

        return redirect()
            ->back()
            ->with('success', 'Deployment settings updated successfully.');
    }

    public function regenerateToken(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $site->regenerateDeployHookToken();

        return redirect()
            ->back()
            ->with('success', 'Deploy hook token regenerated.');
    }
}
