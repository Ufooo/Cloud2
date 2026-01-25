<?php

namespace Nip\BackgroundProcess\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Enums\StopSignal;
use Nip\BackgroundProcess\Http\Requests\StoreSiteBackgroundProcessRequest;
use Nip\BackgroundProcess\Http\Requests\UpdateSiteBackgroundProcessRequest;
use Nip\BackgroundProcess\Http\Resources\BackgroundProcessResource;
use Nip\BackgroundProcess\Jobs\RemoveBackgroundProcessJob;
use Nip\BackgroundProcess\Jobs\RestartBackgroundProcessJob;
use Nip\BackgroundProcess\Jobs\StartBackgroundProcessJob;
use Nip\BackgroundProcess\Jobs\StopBackgroundProcessJob;
use Nip\BackgroundProcess\Jobs\SyncBackgroundProcessJob;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Services\SSH\SSHService;
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

        $phpVersions = $site->server->phpVersions()
            ->where('status', \Nip\Php\Enums\PhpVersionStatus::Installed)
            ->orderBy('version', 'desc')
            ->get()
            ->map(fn ($v) => [
                'version' => $v->version,
                'binary' => "php{$v->version}",
            ])
            ->values()
            ->all();

        return Inertia::render('sites/BackgroundProcesses', [
            'site' => SiteData::fromModel($site),
            'processes' => BackgroundProcessResource::collection($processes),
            'users' => $users,
            'stopSignals' => StopSignal::options(),
            'phpVersions' => $phpVersions,
        ]);
    }

    public function store(StoreSiteBackgroundProcessRequest $request, Site $site): RedirectResponse
    {
        $process = $site->backgroundProcesses()->create([
            ...$request->validated(),
            'server_id' => $site->server_id,
            'status' => ProcessStatus::Installing,
        ]);

        SyncBackgroundProcessJob::dispatch($process);

        return redirect()
            ->route('sites.background-processes', $site)->with('success', 'Background process creation started.');
    }

    public function update(UpdateSiteBackgroundProcessRequest $request, Site $site, BackgroundProcess $process): RedirectResponse
    {
        abort_unless($process->site_id === $site->id, 403);

        $process->update([
            ...$request->validated(),
            'status' => ProcessStatus::Installing,
        ]);

        SyncBackgroundProcessJob::dispatch($process);

        return redirect()
            ->route('sites.background-processes', $site)->with('success', 'Background process update started.');
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
        abort_if(
            $process->status === ProcessStatus::Deleting,
            403,
            'Process deletion is already in progress.'
        );

        $process->update(['status' => ProcessStatus::Deleting]);

        RemoveBackgroundProcessJob::dispatch($process);

        return redirect()
            ->route('sites.background-processes', $site)->with('success', 'Background process deletion started.');
    }

    public function restart(Site $site, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($process->site_id === $site->id, 403);
        abort_unless($process->status === ProcessStatus::Installed, 403, 'Process must be installed to restart.');

        RestartBackgroundProcessJob::dispatch($process);

        return redirect()
            ->route('sites.background-processes', $site)->with('success', 'Background process restart initiated.');
    }

    public function start(Site $site, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($process->site_id === $site->id, 403);
        abort_unless($process->status === ProcessStatus::Installed, 403, 'Process must be installed to start.');

        StartBackgroundProcessJob::dispatch($process);

        return redirect()
            ->route('sites.background-processes', $site)->with('success', 'Background process start initiated.');
    }

    public function stop(Site $site, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($process->site_id === $site->id, 403);
        abort_unless($process->status === ProcessStatus::Installed, 403, 'Process must be installed to stop.');

        StopBackgroundProcessJob::dispatch($process);

        return redirect()
            ->route('sites.background-processes', $site)->with('success', 'Background process stop initiated.');
    }

    public function viewLogs(Site $site, BackgroundProcess $process, SSHService $ssh): JsonResponse
    {
        Gate::authorize('view', $site->server);

        abort_unless($process->site_id === $site->id, 403);

        $logPath = "/home/{$process->user}/.forge/{$process->name}.log";

        try {
            $ssh->connect($site->server);
            $logContent = $ssh->getFileContent($logPath) ?? '';
        } catch (\Exception $e) {
            return response()->json([
                'content' => '',
                'error' => 'Failed to read log file: '.$e->getMessage(),
            ], 500);
        } finally {
            $ssh->disconnect();
        }

        return response()->json([
            'content' => $logContent,
        ]);
    }

    public function viewStatus(Site $site, BackgroundProcess $process, SSHService $ssh): JsonResponse
    {
        Gate::authorize('view', $site->server);

        abort_unless($process->site_id === $site->id, 403);

        try {
            $ssh->connect($site->server);
            $result = $ssh->exec("supervisorctl status {$process->name}");
            $statusOutput = $result->output;
        } catch (\Exception $e) {
            return response()->json([
                'status' => '',
                'error' => 'Failed to get process status: '.$e->getMessage(),
            ], 500);
        } finally {
            $ssh->disconnect();
        }

        return response()->json([
            'status' => $statusOutput,
        ]);
    }

    public function clearLogs(Site $site, BackgroundProcess $process, SSHService $ssh): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($process->site_id === $site->id, 403);

        $logPath = "/home/{$process->user}/.forge/{$process->name}.log";

        try {
            $ssh->connect($site->server);
            $ssh->exec("truncate -s 0 {$logPath}");
        } catch (\Exception $e) {
            return redirect()
                ->route('sites.background-processes', $site)
                ->with('error', 'Failed to clear log file: '.$e->getMessage());
        } finally {
            $ssh->disconnect();
        }

        return redirect()
            ->route('sites.background-processes', $site)
            ->with('success', 'Background process logs cleared successfully.');
    }
}
