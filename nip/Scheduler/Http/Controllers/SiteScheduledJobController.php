<?php

namespace Nip\Scheduler\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Scheduler\Enums\CronFrequency;
use Nip\Scheduler\Enums\GracePeriod;
use Nip\Scheduler\Enums\JobStatus;
use Nip\Scheduler\Http\Requests\StoreSiteScheduledJobRequest;
use Nip\Scheduler\Http\Requests\UpdateSiteScheduledJobRequest;
use Nip\Scheduler\Http\Resources\ScheduledJobResource;
use Nip\Scheduler\Models\ScheduledJob;
use Nip\Site\Data\SiteData;
use Nip\Site\Models\Site;

class SiteScheduledJobController extends Controller
{
    public function index(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $site->load('server');

        $jobs = $site->scheduledJobs()
            ->orderBy('name')
            ->paginate(10);

        $users = $site->server->unixUsers()
            ->orderBy('username')
            ->pluck('username')
            ->toArray();

        return Inertia::render('sites/Scheduler', [
            'site' => SiteData::fromModel($site),
            'jobs' => ScheduledJobResource::collection($jobs),
            'users' => $users,
            'frequencies' => CronFrequency::options(),
            'gracePeriods' => GracePeriod::options(),
        ]);
    }

    public function store(StoreSiteScheduledJobRequest $request, Site $site): RedirectResponse
    {
        $data = $request->validated();

        if ($data['heartbeat_enabled'] ?? false) {
            $data['heartbeat_url'] = $this->generateHeartbeatUrl();
        }

        $site->scheduledJobs()->create([
            ...$data,
            'server_id' => $site->server_id,
            'status' => JobStatus::Pending,
        ]);

        return redirect()
            ->route('sites.scheduler', $site)
            ->with('success', 'Scheduled job created successfully.');
    }

    public function update(UpdateSiteScheduledJobRequest $request, Site $site, ScheduledJob $job): RedirectResponse
    {
        abort_unless($job->site_id === $site->id, 403);

        $data = $request->validated();

        if (($data['heartbeat_enabled'] ?? false) && ! $job->heartbeat_url) {
            $data['heartbeat_url'] = $this->generateHeartbeatUrl();
        }

        if (! ($data['heartbeat_enabled'] ?? $job->heartbeat_enabled)) {
            $data['heartbeat_url'] = null;
            $data['grace_period'] = null;
        }

        $job->update($data);

        return redirect()
            ->route('sites.scheduler', $site)
            ->with('success', 'Scheduled job updated successfully.');
    }

    public function destroy(Site $site, ScheduledJob $job): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($job->site_id === $site->id, 403);
        abort_if(
            $job->status === JobStatus::Installing,
            403,
            'Cannot delete a job while installation is in progress.'
        );

        $job->delete();

        return redirect()
            ->route('sites.scheduler', $site)
            ->with('success', 'Scheduled job deleted successfully.');
    }

    public function pause(Site $site, ScheduledJob $job): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($job->site_id === $site->id, 403);
        abort_unless($job->status === JobStatus::Installed, 403, 'Job must be installed to pause.');

        $job->update(['status' => JobStatus::Paused]);

        return redirect()
            ->route('sites.scheduler', $site)
            ->with('success', 'Scheduled job paused successfully.');
    }

    public function resume(Site $site, ScheduledJob $job): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($job->site_id === $site->id, 403);
        abort_unless($job->status === JobStatus::Paused, 403, 'Job must be paused to resume.');

        $job->update(['status' => JobStatus::Installed]);

        return redirect()
            ->route('sites.scheduler', $site)
            ->with('success', 'Scheduled job resumed successfully.');
    }

    private function generateHeartbeatUrl(): string
    {
        return url('/heartbeat/'.bin2hex(random_bytes(16)));
    }
}
