<?php

namespace Nip\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Security\Enums\SecurityRuleStatus;
use Nip\Security\Http\Requests\StoreSecurityRuleRequest;
use Nip\Security\Http\Requests\UpdateSecurityRuleRequest;
use Nip\Security\Http\Resources\SecurityRuleResource;
use Nip\Security\Models\SecurityRule;
use Nip\Site\Data\SiteData;
use Nip\Site\Models\Site;

class SiteSecurityRuleController extends Controller
{
    public function index(Site $site): Response
    {
        Gate::authorize('view', $site->server);

        $site->load('server');

        $rules = $site->securityRules()
            ->with('credentials')
            ->orderBy('name')
            ->paginate(10);

        return Inertia::render('sites/Security', [
            'site' => SiteData::fromModel($site),
            'rules' => SecurityRuleResource::collection($rules),
        ]);
    }

    public function store(StoreSecurityRuleRequest $request, Site $site): RedirectResponse
    {
        $data = $request->validated();

        $rule = $site->securityRules()->create([
            'name' => $data['name'],
            'path' => $data['path'] ?? null,
            'status' => SecurityRuleStatus::Pending,
        ]);

        foreach ($data['credentials'] as $credential) {
            $rule->credentials()->create([
                'username' => $credential['username'],
                'password' => Hash::make($credential['password']),
            ]);
        }

        return redirect()
            ->route('sites.security', $site)->with('success', 'Security rule created successfully.');
    }

    public function update(UpdateSecurityRuleRequest $request, Site $site, SecurityRule $rule): RedirectResponse
    {
        abort_unless($rule->site_id === $site->id, 403);

        $data = $request->validated();

        $rule->update([
            'name' => $data['name'] ?? $rule->name,
            'path' => $data['path'] ?? $rule->path,
        ]);

        if (isset($data['credentials'])) {
            $rule->credentials()->delete();

            foreach ($data['credentials'] as $credential) {
                $rule->credentials()->create([
                    'username' => $credential['username'],
                    'password' => Hash::make($credential['password']),
                ]);
            }
        }

        return redirect()
            ->route('sites.security', $site)->with('success', 'Security rule updated successfully.');
    }

    public function destroy(Site $site, SecurityRule $rule): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($rule->site_id === $site->id, 403);
        abort_if(
            $rule->status === SecurityRuleStatus::Installing,
            403,
            'Cannot delete a rule while installation is in progress.'
        );

        $rule->delete();

        return redirect()
            ->route('sites.security', $site)->with('success', 'Security rule deleted successfully.');
    }
}
