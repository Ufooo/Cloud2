<?php

namespace Nip\Composer\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Composer\Enums\ComposerCredentialStatus;
use Nip\Composer\Http\Requests\StoreServerComposerCredentialRequest;
use Nip\Composer\Http\Requests\UpdateServerComposerCredentialRequest;
use Nip\Composer\Http\Resources\ComposerCredentialResource;
use Nip\Composer\Jobs\SyncServerComposerAuthJob;
use Nip\Composer\Models\ComposerCredential;
use Nip\Server\Data\ServerData;
use Nip\Server\Models\Server;

class ServerComposerController extends Controller
{
    use LoadsServerPermissions;

    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        $this->loadServerPermissions($server);

        $userLevelCredentials = ComposerCredential::query()
            ->whereIn('unix_user_id', $server->unixUsers->pluck('id'))
            ->whereNull('site_id')
            ->with('unixUser')
            ->get();

        return Inertia::render('servers/composer/Index', [
            'server' => ServerData::from($server),
            'credentials' => ComposerCredentialResource::collection($userLevelCredentials),
            'users' => $server->unixUsers->pluck('username')->toArray(),
        ]);
    }

    public function store(Server $server, StoreServerComposerCredentialRequest $request): RedirectResponse
    {
        Gate::authorize('update', $server);

        $unixUser = $server->unixUsers()->where('username', $request->validated()['user'])->firstOrFail();

        ComposerCredential::create([
            'unix_user_id' => $unixUser->id,
            'site_id' => null,
            'repository' => $request->validated()['repository'],
            'username' => $request->validated()['username'],
            'password' => $request->validated()['password'],
        ]);

        SyncServerComposerAuthJob::dispatch($unixUser);

        return back()->with('success', 'Credential added.');
    }

    public function update(Server $server, ComposerCredential $credential, UpdateServerComposerCredentialRequest $request): RedirectResponse
    {
        Gate::authorize('update', $server);

        $serverUnixUserIds = $server->unixUsers->pluck('id')->toArray();
        abort_if(! in_array($credential->unix_user_id, $serverUnixUserIds) || $credential->site_id !== null, 404);

        $oldUnixUser = $credential->unixUser;
        $newUnixUser = $server->unixUsers()->where('username', $request->validated()['user'])->firstOrFail();

        $credential->update([
            'unix_user_id' => $newUnixUser->id,
            'repository' => $request->validated()['repository'],
            'username' => $request->validated()['username'],
            'password' => $request->validated()['password'],
        ]);

        SyncServerComposerAuthJob::dispatch($newUnixUser);

        if ($oldUnixUser->id !== $newUnixUser->id) {
            SyncServerComposerAuthJob::dispatch($oldUnixUser);
        }

        return back()->with('success', 'Credential updated.');
    }

    public function destroy(Server $server, ComposerCredential $credential): RedirectResponse
    {
        Gate::authorize('update', $server);

        $serverUnixUserIds = $server->unixUsers->pluck('id')->toArray();
        abort_if(! in_array($credential->unix_user_id, $serverUnixUserIds) || $credential->site_id !== null, 404);

        $credential->update(['status' => ComposerCredentialStatus::Deleting]);

        SyncServerComposerAuthJob::dispatch($credential->unixUser, $credential->id);

        return back()->with('success', 'Credential removed.');
    }
}
