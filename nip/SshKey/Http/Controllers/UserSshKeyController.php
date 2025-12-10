<?php

namespace Nip\SshKey\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Nip\SshKey\Http\Requests\StoreUserSshKeyRequest;
use Nip\SshKey\Http\Resources\UserSshKeyResource;
use Nip\SshKey\Models\UserSshKey;

class UserSshKeyController extends Controller
{
    public function index(): Response
    {
        $keys = auth()->user()
            ->sshKeys()
            ->orderBy('name')
            ->paginate(10);

        return Inertia::render('settings/SshKeys', [
            'keys' => UserSshKeyResource::collection($keys),
        ]);
    }

    public function store(StoreUserSshKeyRequest $request): RedirectResponse
    {
        auth()->user()->sshKeys()->create($request->validated());

        return redirect()
            ->route('settings.ssh-keys')
            ->with('success', 'SSH key added successfully.');
    }

    public function destroy(UserSshKey $key): RedirectResponse
    {
        abort_unless($key->user_id === auth()->id(), 403);

        $key->delete();

        return redirect()
            ->route('settings.ssh-keys')
            ->with('success', 'SSH key deleted successfully.');
    }
}
