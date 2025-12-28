<?php

namespace Nip\Composer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Composer\Enums\ComposerCredentialStatus;
use Nip\Composer\Http\Requests\StoreComposerCredentialRequest;
use Nip\Composer\Http\Requests\UpdateComposerCredentialRequest;
use Nip\Composer\Http\Resources\ComposerCredentialResource;
use Nip\Composer\Jobs\SyncComposerAuthJob;
use Nip\Composer\Models\ComposerCredential;
use Nip\Site\Data\SiteData;
use Nip\Site\Models\Site;

class ComposerController extends Controller
{
    public function index(Site $site): Response
    {
        return Inertia::render('sites/composer/Index', [
            'site' => SiteData::fromModel($site),
            'credentials' => ComposerCredentialResource::collection($site->composerCredentials),
        ]);
    }

    public function store(Site $site, StoreComposerCredentialRequest $request): RedirectResponse
    {
        $site->composerCredentials()->create([
            ...$request->validated(),
            'unix_user_id' => $site->unixUser->id,
        ]);

        SyncComposerAuthJob::dispatch($site);

        return back()->with('success', 'Credential added.');
    }

    public function update(Site $site, ComposerCredential $credential, UpdateComposerCredentialRequest $request): RedirectResponse
    {
        abort_if($credential->site_id !== $site->id, 404);

        $credential->update($request->validated());

        SyncComposerAuthJob::dispatch($site);

        return back()->with('success', 'Credential updated.');
    }

    public function destroy(Site $site, ComposerCredential $credential): RedirectResponse
    {
        abort_if($credential->site_id !== $site->id, 404);

        $credential->update(['status' => ComposerCredentialStatus::Deleting]);

        SyncComposerAuthJob::dispatch($site, $credential->id);

        return back()->with('success', 'Credential removed.');
    }
}
