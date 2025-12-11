<?php

namespace Nip\Scheduler\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Scheduler\Enums\CronFrequency;
use Nip\Scheduler\Enums\GracePeriod;
use Nip\Scheduler\Enums\JobStatus;
use Nip\Scheduler\Http\Requests\StoreScheduledJobRequest;
use Nip\Scheduler\Http\Requests\UpdateScheduledJobRequest;
use Nip\Scheduler\Http\Resources\ScheduledJobResource;
use Nip\Scheduler\Models\ScheduledJob;
use Nip\Server\Data\ServerData;
use Nip\Server\Models\Server;

class ScheduledJobController extends Controller
{
    use LoadsServerPermissions;

    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        $this->loadServerPermissions($server);

        $jobs = $server->scheduledJobs()
            ->orderBy('name')
            ->paginate(10);

        $users = $server->unixUsers()
            ->orderBy('username')
            ->pluck('username')
            ->toArray();

        return Inertia::render('servers/Scheduler', [
            'server' => ServerData::from($server),
            'jobs' => ScheduledJobResource::collection($jobs),
            'users' => $users,
            'frequencies' => CronFrequency::options(),
            'gracePeriods' => GracePeriod::options(),
        ]);
    }

    public function store(StoreScheduledJobRequest $request, Server $server): RedirectResponse
    {
        $data = $request->validated();

        if ($data['heartbeat_enabled'] ?? false) {
            $data['heartbeat_url'] = $this->generateHeartbeatUrl();
        }

        $server->scheduledJobs()->create([
            ...$data,
            'status' => JobStatus::Pending,
        ]);

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', 'Scheduled job created successfully.');
    }

    public function update(UpdateScheduledJobRequest $request, Server $server, ScheduledJob $job): RedirectResponse
    {
        abort_unless($job->server_id === $server->id, 403);

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
            ->route('servers.scheduler', $server)
            ->with('success', 'Scheduled job updated successfully.');
    }

    public function destroy(Server $server, ScheduledJob $job): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($job->server_id === $server->id, 403);
        abort_if(
            $job->status === JobStatus::Installing,
            403,
            'Cannot delete a job while installation is in progress.'
        );

        $job->delete();

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', 'Scheduled job deleted successfully.');
    }

    public function pause(Server $server, ScheduledJob $job): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($job->server_id === $server->id, 403);
        abort_unless($job->status === JobStatus::Installed, 403, 'Job must be installed to pause.');

        $job->update(['status' => JobStatus::Paused]);

        // TODO: Dispatch job to pause the cron on the server

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', 'Scheduled job paused successfully.');
    }

    public function resume(Server $server, ScheduledJob $job): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($job->server_id === $server->id, 403);
        abort_unless($job->status === JobStatus::Paused, 403, 'Job must be paused to resume.');

        $job->update(['status' => JobStatus::Installed]);

        // TODO: Dispatch job to resume the cron on the server

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', 'Scheduled job resumed successfully.');
    }

    private function generateHeartbeatUrl(): string
    {
        return url('/heartbeat/'.bin2hex(random_bytes(16)));
    }
}
