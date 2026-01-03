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
use Nip\Scheduler\Jobs\RemoveScheduledJobJob;
use Nip\Scheduler\Jobs\SyncScheduledJobJob;
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

        $job = $site->scheduledJobs()->create([
            ...$data,
            'server_id' => $site->server_id,
            'status' => JobStatus::Installing,
        ]);

        SyncScheduledJobJob::dispatch($job);

        return redirect()
            ->route('sites.scheduler', $site)->with('success', 'Scheduled job creation started.');
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

        $job->update([
            ...$data,
            'status' => JobStatus::Installing,
        ]);

        SyncScheduledJobJob::dispatch($job);

        return redirect()
            ->route('sites.scheduler', $site)->with('success', 'Scheduled job update started.');
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
        abort_if(
            $job->status === JobStatus::Deleting,
            403,
            'Job deletion is already in progress.'
        );

        // If deleting Laravel Scheduler, update packages
        if ($job->name === 'Laravel Scheduler') {
            $packages = $site->packages ?? [];
            unset($packages['scheduler']);
            $site->update(['packages' => $packages]);
        }

        $job->update(['status' => JobStatus::Deleting]);

        RemoveScheduledJobJob::dispatch($job);

        return redirect()
            ->route('sites.scheduler', $site)->with('success', 'Scheduled job deletion started.');
    }

    public function enableLaravelScheduler(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        // Check if Laravel scheduler already exists
        $existingScheduler = $site->scheduledJobs()
            ->where('name', 'Laravel Scheduler')
            ->first();

        if ($existingScheduler) {
            return redirect()
                ->route('sites.scheduler', $site)->with('info', 'Laravel Scheduler is already configured.');
        }

        $phpVersion = $site->getEffectivePhpVersion();
        $artisanPath = $site->getCurrentPath().'/artisan';

        $job = $site->scheduledJobs()->create([
            'server_id' => $site->server_id,
            'name' => 'Laravel Scheduler',
            'command' => "php{$phpVersion} {$artisanPath} schedule:run",
            'user' => $site->user,
            'frequency' => CronFrequency::EveryMinute,
            'status' => JobStatus::Installing,
        ]);

        // Note: packages['scheduler'] will be set to true in SyncScheduledJobJob::handleSuccess

        SyncScheduledJobJob::dispatch($job);

        return redirect()
            ->route('sites.scheduler', $site)->with('success', 'Laravel Scheduler is being enabled.');

        return redirect()
            ->back();
    }

    public function pause(Site $site, ScheduledJob $job): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($job->site_id === $site->id, 403);
        abort_unless($job->status === JobStatus::Installed, 403, 'Job must be installed to pause.');

        // If pausing Laravel Scheduler, update packages
        if ($job->name === 'Laravel Scheduler') {
            $packages = $site->packages ?? [];
            unset($packages['scheduler']);
            $site->update(['packages' => $packages]);
        }

        $job->update(['status' => JobStatus::Paused]);

        RemoveScheduledJobJob::dispatch($job);

        return redirect()
            ->route('sites.scheduler', $site)->with('success', 'Scheduled job pause started.');
    }

    public function resume(Site $site, ScheduledJob $job): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($job->site_id === $site->id, 403);
        abort_unless($job->status === JobStatus::Paused, 403, 'Job must be paused to resume.');

        // If resuming Laravel Scheduler, update packages
        if ($job->name === 'Laravel Scheduler') {
            $packages = $site->packages ?? [];
            $packages['scheduler'] = true;
            $site->update(['packages' => $packages]);
        }

        $job->update(['status' => JobStatus::Installing]);

        SyncScheduledJobJob::dispatch($job);
    }

    private function generateHeartbeatUrl(): string
    {
        return url('/heartbeat/'.bin2hex(random_bytes(16)));
    }
}
