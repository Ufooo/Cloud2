<?php

namespace Nip\Database\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Database\Http\Requests\StoreDatabaseRequest;
use Nip\Database\Http\Requests\StoreDatabaseUserRequest;
use Nip\Database\Http\Requests\UpdateDatabaseUserRequest;
use Nip\Database\Http\Resources\DatabaseResource;
use Nip\Database\Http\Resources\DatabaseUserResource;
use Nip\Database\Models\Database;
use Nip\Database\Models\DatabaseUser;
use Nip\Server\Data\ServerData;
use Nip\Server\Models\Server;
use Nip\Site\Data\SiteData;
use Nip\Site\Models\Site;

class DatabaseController extends Controller
{
    use LoadsServerPermissions;

    public function index(): Response
    {
        $databases = Database::query()
            ->with(['server', 'site'])
            ->orderBy('name')
            ->paginate(15);

        $databaseUsers = DatabaseUser::query()
            ->with('server')
            ->withCount('databases')
            ->orderBy('username')
            ->paginate(15);

        return Inertia::render('databases/Index', [
            'databases' => DatabaseResource::collection($databases),
            'databaseUsers' => DatabaseUserResource::collection($databaseUsers),
            'server' => null,
            'site' => null,
        ]);
    }

    public function indexForServer(Server $server): Response
    {
        Gate::authorize('view', $server);

        $this->loadServerPermissions($server);

        $databases = Database::query()
            ->where('server_id', $server->id)
            ->with(['server', 'site'])
            ->orderBy('name')
            ->paginate(15);

        $databaseUsers = DatabaseUser::query()
            ->where('server_id', $server->id)
            ->with(['server', 'databases'])
            ->withCount('databases')
            ->orderBy('username')
            ->paginate(15);

        return Inertia::render('databases/Index', [
            'databases' => DatabaseResource::collection($databases),
            'databaseUsers' => DatabaseUserResource::collection($databaseUsers),
            'server' => ServerData::from($server),
            'site' => null,
        ]);
    }

    public function indexForSite(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $site->load('server');

        $databases = Database::query()
            ->where('site_id', $site->id)
            ->with(['server', 'site'])
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('databases/Index', [
            'databases' => DatabaseResource::collection($databases),
            'databaseUsers' => [],
            'server' => null,
            'site' => SiteData::fromModel($site),
        ]);
    }

    public function store(StoreDatabaseRequest $request, Server $server): RedirectResponse
    {
        Gate::authorize('update', $server);

        $database = Database::create([
            'server_id' => $server->id,
            'name' => $request->validated('name'),
        ]);

        // Optionally create a user with access to this database
        if ($request->validated('user')) {
            $user = DatabaseUser::create([
                'server_id' => $server->id,
                'username' => $request->validated('user'),
            ]);

            $user->databases()->attach($database->id);
        }

        return redirect()
            ->back()
            ->with('success', 'Database created successfully.');
    }

    public function destroy(Server $server, Database $database): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_if($database->server_id !== $server->id, 404);

        $database->delete();

        return redirect()
            ->back()
            ->with('success', 'Database deleted successfully.');
    }

    public function storeUser(StoreDatabaseUserRequest $request, Server $server): RedirectResponse
    {
        Gate::authorize('update', $server);

        $user = DatabaseUser::create([
            'server_id' => $server->id,
            'username' => $request->validated('username'),
            'readonly' => $request->validated('readonly', false),
        ]);

        // Attach selected databases
        $databases = $request->validated('databases', []);
        if (! empty($databases)) {
            $user->databases()->attach($databases);
        }

        return redirect()
            ->back()
            ->with('success', 'Database user created successfully.');
    }

    public function updateUser(UpdateDatabaseUserRequest $request, Server $server, DatabaseUser $databaseUser): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_if($databaseUser->server_id !== $server->id, 404);

        // Update readonly status
        $databaseUser->update([
            'readonly' => $request->validated('readonly', $databaseUser->readonly),
        ]);

        // Sync selected databases
        $databases = $request->validated('databases', []);
        $databaseUser->databases()->sync($databases);

        return redirect()
            ->back()
            ->with('success', 'Database user updated successfully.');
    }

    public function destroyUser(Server $server, DatabaseUser $databaseUser): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_if($databaseUser->server_id !== $server->id, 404);

        $databaseUser->delete();

        return redirect()
            ->back()
            ->with('success', 'Database user deleted successfully.');
    }
}
