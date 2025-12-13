<?php

namespace Nip\BackgroundProcess\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Enums\StopSignal;
use Nip\BackgroundProcess\Http\Requests\StoreSiteBackgroundProcessRequest;
use Nip\BackgroundProcess\Http\Requests\UpdateSiteBackgroundProcessRequest;
use Nip\BackgroundProcess\Http\Resources\BackgroundProcessResource;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Site\Data\SiteData;
use Nip\Site\Models\Site;

class SiteBackgroundProcessController extends Controller
{
    public function index(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $site->load('server');

        $processes = $site->backgroundProcesses()
            ->orderBy('name')
            ->paginate(10);

        $users = $site->server->unixUsers()
            ->orderBy('username')
            ->pluck('username')
            ->toArray();

        return Inertia::render('sites/BackgroundProcesses', [
            'site' => SiteData::fromModel($site),
            'processes' => BackgroundProcessResource::collection($processes),
            'users' => $users,
            'stopSignals' => StopSignal::options(),
        ]);
    }

    public function store(StoreSiteBackgroundProcessRequest $request, Site $site): RedirectResponse
    {
        $site->backgroundProcesses()->create([
            ...$request->validated(),
            'server_id' => $site->server_id,
            'status' => ProcessStatus::Pending,
        ]);

        return redirect()
            ->route('sites.background-processes', $site)
            ->with('success', 'Background process created successfully.');
    }

    public function update(UpdateSiteBackgroundProcessRequest $request, Site $site, BackgroundProcess $process): RedirectResponse
    {
        abort_unless($process->site_id === $site->id, 403);

        $process->update($request->validated());

        return redirect()
            ->route('sites.background-processes', $site)
            ->with('success', 'Background process updated successfully.');
    }

    public function destroy(Site $site, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($process->site_id === $site->id, 403);
        abort_if(
            $process->status === ProcessStatus::Installing,
            403,
            'Cannot delete a process while installation is in progress.'
        );

        $process->delete();

        return redirect()
            ->route('sites.background-processes', $site)
            ->with('success', 'Background process deleted successfully.');
    }

    public function restart(Site $site, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($process->site_id === $site->id, 403);
        abort_unless($process->status === ProcessStatus::Installed, 403, 'Process must be installed to restart.');

        return redirect()
            ->route('sites.background-processes', $site)
            ->with('success', 'Background process restart initiated.');
    }

    public function start(Site $site, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($process->site_id === $site->id, 403);
        abort_unless($process->status === ProcessStatus::Installed, 403, 'Process must be installed to start.');

        return redirect()
            ->route('sites.background-processes', $site)
            ->with('success', 'Background process start initiated.');
    }

    public function stop(Site $site, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($process->site_id === $site->id, 403);
        abort_unless($process->status === ProcessStatus::Installed, 403, 'Process must be installed to stop.');

        return redirect()
            ->route('sites.background-processes', $site)
            ->with('success', 'Background process stop initiated.');
    }
}
