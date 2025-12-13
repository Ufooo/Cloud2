<?php

namespace Nip\Redirect\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Redirect\Enums\RedirectRuleStatus;
use Nip\Redirect\Http\Requests\StoreRedirectRuleRequest;
use Nip\Redirect\Http\Requests\UpdateRedirectRuleRequest;
use Nip\Redirect\Http\Resources\RedirectRuleResource;
use Nip\Redirect\Models\RedirectRule;
use Nip\Site\Data\SiteData;
use Nip\Site\Models\Site;

class SiteRedirectRuleController extends Controller
{
    public function index(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $site->load('server');

        $rules = $site->redirectRules()
            ->orderBy('from')
            ->paginate(10);

        return Inertia::render('sites/Redirects', [
            'site' => SiteData::fromModel($site),
            'rules' => RedirectRuleResource::collection($rules),
        ]);
    }

    public function store(StoreRedirectRuleRequest $request, Site $site): RedirectResponse
    {
        $data = $request->validated();

        $site->redirectRules()->create([
            'from' => $data['from'],
            'to' => $data['to'],
            'type' => $data['type'] ?? 'permanent',
            'status' => RedirectRuleStatus::Pending,
        ]);

        return redirect()
            ->route('sites.redirects', $site)
            ->with('success', 'Redirect rule created successfully.');
    }

    public function update(UpdateRedirectRuleRequest $request, Site $site, RedirectRule $rule): RedirectResponse
    {
        abort_unless($rule->site_id === $site->id, 403);

        $data = $request->validated();

        $rule->update([
            'from' => $data['from'] ?? $rule->from,
            'to' => $data['to'] ?? $rule->to,
            'type' => $data['type'] ?? $rule->type,
        ]);

        return redirect()
            ->route('sites.redirects', $site)
            ->with('success', 'Redirect rule updated successfully.');
    }

    public function destroy(Site $site, RedirectRule $rule): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($rule->site_id === $site->id, 403);
        abort_if(
            $rule->status === RedirectRuleStatus::Installing,
            403,
            'Cannot delete a rule while installation is in progress.'
        );

        $rule->delete();

        return redirect()
            ->route('sites.redirects', $site)
            ->with('success', 'Redirect rule deleted successfully.');
    }
}
