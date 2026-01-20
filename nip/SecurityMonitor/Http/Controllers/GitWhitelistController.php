<?php

namespace Nip\SecurityMonitor\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Nip\SecurityMonitor\Actions\CreateGitWhitelist;
use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\SecurityMonitor\Http\Requests\CreateGitWhitelistRequest;
use Nip\SecurityMonitor\Models\SecurityGitWhitelist;
use Nip\Site\Models\Site;

class GitWhitelistController extends Controller
{
    public function store(CreateGitWhitelistRequest $request, CreateGitWhitelist $action): RedirectResponse
    {
        $site = Site::query()->findOrFail($request->validated('site_id'));

        $action->handle(
            site: $site,
            filePath: $request->validated('file_path'),
            changeType: GitChangeType::from($request->validated('change_type')),
            reason: $request->validated('reason'),
        );

        return redirect()->back()->with('success', 'File added to whitelist.');
    }

    public function destroy(SecurityGitWhitelist $whitelist): RedirectResponse
    {
        Gate::authorize('update', $whitelist->site->server);

        $whitelist->delete();

        return redirect()->back()->with('success', 'Whitelist entry removed.');
    }

    public function removeByFile(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $filePath = request()->input('file_path');
        $changeType = request()->input('change_type');

        $query = SecurityGitWhitelist::query()
            ->where('site_id', $site->id)
            ->where('file_path', $filePath);

        if ($changeType && $changeType !== 'any') {
            $query->where('change_type', $changeType);
        }

        $query->delete();

        $deleted = $this->deleteWhitelistedChanges($site, $filePath, $changeType);

        if ($deleted === 0) {
            return redirect()->back()->with('info', 'No whitelisted changes found.');
        }

        return redirect()->back()->with('success', 'Removed from whitelist.');
    }

    private function deleteWhitelistedChanges(Site $site, string $filePath, ?string $changeType): int
    {
        $query = \Nip\SecurityMonitor\Models\SecurityGitChange::query()
            ->whereHas('scan', fn ($q) => $q->where('site_id', $site->id))
            ->where('file_path', $filePath)
            ->where('is_whitelisted', true);

        if ($changeType && $changeType !== 'any') {
            $query->where('change_type', $changeType);
        }

        return $query->delete();
    }

    public function whitelistAll(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        $latestScan = $site->latestSecurityScan;
        if (! $latestScan) {
            return redirect()->back()->with('error', 'No scan found.');
        }

        $newChanges = $latestScan->gitChanges()->where('is_whitelisted', false)->get();
        if ($newChanges->isEmpty()) {
            return redirect()->back()->with('info', 'No new changes to whitelist.');
        }

        $whitelistEntries = $newChanges->map(fn ($change) => [
            'site_id' => $site->id,
            'file_path' => $change->file_path,
            'change_type' => $change->change_type,
            'created_by' => Auth::id(),
            'reason' => 'Bulk whitelisted',
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        SecurityGitWhitelist::insert($whitelistEntries);

        return redirect()->back()->with('success', "Added {$newChanges->count()} files to whitelist.");
    }
}
