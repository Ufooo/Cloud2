<?php

namespace Nip\Scheduler\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Server\Data\ServerData;
use Nip\Server\Models\Server;

class SchedulerController extends Controller
{
    use LoadsServerPermissions;

    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        $this->loadServerPermissions($server);

        return Inertia::render('servers/Scheduler', [
            'server' => ServerData::from($server),
        ]);
    }
}
