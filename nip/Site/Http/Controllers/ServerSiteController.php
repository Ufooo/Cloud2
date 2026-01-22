<?php

namespace Nip\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Http\Resources\ServerResource;
use Nip\Server\Models\Server;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteType;
use Nip\Site\Http\Resources\SiteResource;

class ServerSiteController extends Controller
{
    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        $sites = $server->sites()
            ->with('server')
            ->orderBy('domain')
            ->paginate(15);

        $servers = Server::query()
            ->where('status', 'active')
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
            'currentServer' => ServerResource::make($server),
            'siteTypes' => SiteType::options(),
            'packageManagers' => PackageManager::options(),
            'colors' => IdentityColor::options(),
            'filters' => [
                'search' => null,
            ],
        ]);
    }
}
