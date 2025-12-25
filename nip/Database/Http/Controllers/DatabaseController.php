<?php

namespace Nip\Database\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Database\Enums\DatabaseStatus;
use Nip\Database\Enums\DatabaseUserStatus;
use Nip\Database\Http\Requests\StoreDatabaseRequest;
use Nip\Database\Http\Requests\StoreDatabaseUserRequest;
use Nip\Database\Http\Requests\UpdateDatabaseUserRequest;
use Nip\Database\Http\Resources\DatabaseResource;
use Nip\Database\Http\Resources\DatabaseUserResource;
use Nip\Database\Jobs\CreateDatabaseJob;
use Nip\Database\Jobs\DeleteDatabaseJob;
use Nip\Database\Jobs\DeleteDatabaseUserJob;
use Nip\Database\Jobs\SyncDatabaseUserJob;
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
            'status' => DatabaseStatus::Installing,
        ]);

        CreateDatabaseJob::dispatch($database);

        // Optionally create a user with access to this database
        if ($request->validated('user')) {
            $password = Str::random(20);

            $user = DatabaseUser::create([
                'server_id' => $server->id,
                'username' => $request->validated('user'),
                'password' => $password,
                'status' => DatabaseUserStatus::Installing,
            ]);

            $user->databases()->attach($database->id);

            SyncDatabaseUserJob::dispatch($user);
        }

        return redirect()
            ->back()
            ->with('success', 'Database is being created.');
    }

    public function destroy(Server $server, Database $database): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_if($database->server_id !== $server->id, 404);

        $database->update(['status' => DatabaseStatus::Deleting]);

        DeleteDatabaseJob::dispatch($database);

        return redirect()
            ->back()
            ->with('success', 'Database is being deleted.');
    }

    public function storeUser(StoreDatabaseUserRequest $request, Server $server): RedirectResponse
    {
        Gate::authorize('update', $server);

        $password = Str::random(20);

        $user = DatabaseUser::create([
            'server_id' => $server->id,
            'username' => $request->validated('username'),
            'password' => $password,
            'readonly' => $request->validated('readonly', false),
            'status' => DatabaseUserStatus::Installing,
        ]);

        // Attach selected databases
        $databases = $request->validated('databases', []);
        if (! empty($databases)) {
            $user->databases()->attach($databases);
        }

        SyncDatabaseUserJob::dispatch($user);

        return redirect()
            ->back()
            ->with('success', 'Database user is being created.');
    }

    public function updateUser(UpdateDatabaseUserRequest $request, Server $server, DatabaseUser $databaseUser): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_if($databaseUser->server_id !== $server->id, 404);

        $updateData = [
            'readonly' => $request->validated('readonly', $databaseUser->readonly),
            'status' => DatabaseUserStatus::Syncing,
        ];

        // Update password if provided, or generate one if missing
        if ($request->filled('password')) {
            $updateData['password'] = $request->validated('password');
        } elseif (! $databaseUser->password) {
            $updateData['password'] = Str::random(20);
        }

        $databaseUser->update($updateData);

        // Sync selected databases
        $databases = $request->validated('databases', []);
        $databaseUser->databases()->sync($databases);

        SyncDatabaseUserJob::dispatch($databaseUser);

        return redirect()
            ->back()
            ->with('success', 'Database user is being updated.');
    }

    public function destroyUser(Server $server, DatabaseUser $databaseUser): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_if($databaseUser->server_id !== $server->id, 404);

        $databaseUser->update(['status' => DatabaseUserStatus::Deleting]);

        DeleteDatabaseUserJob::dispatch($databaseUser);

        return redirect()
            ->back()
            ->with('success', 'Database user is being deleted.');
    }
}
