<?php

namespace Nip\BackgroundProcess\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Enums\StopSignal;
use Nip\BackgroundProcess\Http\Requests\StoreBackgroundProcessRequest;
use Nip\BackgroundProcess\Http\Requests\UpdateBackgroundProcessRequest;
use Nip\BackgroundProcess\Http\Resources\BackgroundProcessResource;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Server\Data\ServerData;
use Nip\Server\Models\Server;

class BackgroundProcessController extends Controller
{
    use LoadsServerPermissions;

    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        $this->loadServerPermissions($server);

        $processes = $server->backgroundProcesses()
            ->orderBy('name')
            ->paginate(10);

        $users = $server->unixUsers()
            ->orderBy('username')
            ->pluck('username')
            ->toArray();

        return Inertia::render('servers/BackgroundProcesses', [
            'server' => ServerData::from($server),
            'processes' => BackgroundProcessResource::collection($processes),
            'users' => $users,
            'stopSignals' => StopSignal::options(),
        ]);
    }

    public function store(StoreBackgroundProcessRequest $request, Server $server): RedirectResponse
    {
        $server->backgroundProcesses()->create([
            ...$request->validated(),
            'status' => ProcessStatus::Pending,
        ]);

        return redirect()
            ->route('servers.background-processes', $server)
            ->with('success', 'Background process created successfully.');
    }

    public function update(UpdateBackgroundProcessRequest $request, Server $server, BackgroundProcess $process): RedirectResponse
    {
        abort_unless($process->server_id === $server->id, 403);

        $process->update($request->validated());

        return redirect()
            ->route('servers.background-processes', $server)
            ->with('success', 'Background process updated successfully.');
    }

    public function destroy(Server $server, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($process->server_id === $server->id, 403);
        abort_if(
            $process->status === ProcessStatus::Installing,
            403,
            'Cannot delete a process while installation is in progress.'
        );

        $process->delete();

        return redirect()
            ->route('servers.background-processes', $server)
            ->with('success', 'Background process deleted successfully.');
    }

    public function restart(Server $server, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($process->server_id === $server->id, 403);
        abort_unless($process->status === ProcessStatus::Installed, 403, 'Process must be installed to restart.');

        // TODO: Dispatch job to restart the process on the server

        return redirect()
            ->route('servers.background-processes', $server)
            ->with('success', 'Background process restart initiated.');
    }

    public function start(Server $server, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($process->server_id === $server->id, 403);
        abort_unless($process->status === ProcessStatus::Installed, 403, 'Process must be installed to start.');

        // TODO: Dispatch job to start the process on the server

        return redirect()
            ->route('servers.background-processes', $server)
            ->with('success', 'Background process start initiated.');
    }

    public function stop(Server $server, BackgroundProcess $process): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($process->server_id === $server->id, 403);
        abort_unless($process->status === ProcessStatus::Installed, 403, 'Process must be installed to stop.');

        // TODO: Dispatch job to stop the process on the server

        return redirect()
            ->route('servers.background-processes', $server)
            ->with('success', 'Background process stop initiated.');
    }
}
