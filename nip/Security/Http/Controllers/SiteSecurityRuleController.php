<?php

namespace Nip\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Security\Enums\SecurityRuleStatus;
use Nip\Security\Http\Requests\StoreSecurityRuleRequest;
use Nip\Security\Http\Requests\UpdateSecurityRuleRequest;
use Nip\Security\Http\Resources\SecurityRuleResource;
use Nip\Security\Jobs\AddSecurityRuleJob;
use Nip\Security\Jobs\RemoveSecurityRuleJob;
use Nip\Security\Jobs\UpdateSecurityRuleJob;
use Nip\Security\Models\SecurityRule;
use Nip\Site\Data\SiteData;
use Nip\Site\Models\Site;

class SiteSecurityRuleController extends Controller
{
    /**
     * bcrypt cost of the stored password hashes. nginx verifies the hash on every request and
     * browsers resend the Authorization header with each asset: cost 12 burns ~333 ms of CPU
     * per check, cost 5 about 2.6 ms.
     */
    private const PASSWORD_HASH_COST = 5;

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

        $rule = DB::transaction(function () use ($site, $data): SecurityRule {
            $rule = $site->securityRules()->create([
                'name' => $data['name'],
                'path' => $data['path'] ?? null,
                'status' => SecurityRuleStatus::Installing,
            ]);

            $rule->credentials()->createMany($this->hashCredentials($data['credentials']));

            return $rule;
        });

        AddSecurityRuleJob::dispatch($rule);

        return redirect()
            ->route('sites.security', $site)->with('success', 'Security rule created and is being installed.');
    }

    public function update(UpdateSecurityRuleRequest $request, Site $site, SecurityRule $rule): RedirectResponse
    {
        abort_unless($rule->site_id === $site->id, 403);
        $this->abortWhileBeingInstalledOrRemoved($rule, 'update');

        $data = $request->validated();

        DB::transaction(function () use ($rule, $data): void {
            $rule->update([
                'name' => $data['name'] ?? $rule->name,
                // A submitted null means "the whole site", so only an absent key keeps the stored path.
                'path' => array_key_exists('path', $data) ? $data['path'] : $rule->path,
                'status' => SecurityRuleStatus::Updating,
            ]);

            if (isset($data['credentials'])) {
                $rule->credentials()->delete();
                $rule->credentials()->createMany($this->hashCredentials($data['credentials']));
            }
        });

        UpdateSecurityRuleJob::dispatch($rule);

        return redirect()
            ->route('sites.security', $site)->with('success', 'Security rule is being updated.');
    }

    public function destroy(Site $site, SecurityRule $rule): RedirectResponse
    {
        Gate::authorize('update', $site->server);

        abort_unless($rule->site_id === $site->id, 403);
        $this->abortWhileBeingInstalledOrRemoved($rule, 'delete');

        // The row stays until RemoveSecurityRuleJob has taken the directives out of nginx; deleting it
        // here would leave a live auth_basic behind with nothing left that knows how to remove it.
        $rule->update(['status' => SecurityRuleStatus::Removing]);

        RemoveSecurityRuleJob::dispatch($rule);

        return redirect()
            ->route('sites.security', $site)->with('success', 'Security rule is being removed.');
    }

    /**
     * A job is still working on the rule: changing it now would race that job (an update would
     * even bring a rule back to life that is on its way out).
     */
    private function abortWhileBeingInstalledOrRemoved(SecurityRule $rule, string $action): void
    {
        abort_if(
            in_array($rule->status, [SecurityRuleStatus::Installing, SecurityRuleStatus::Removing], true),
            403,
            "Cannot {$action} a rule while it is being installed or removed."
        );
    }

    /**
     * nginx checks the passwords with crypt(), which reads bcrypt but not argon2, so the driver is
     * named explicitly instead of following whatever the application's default hasher is.
     *
     * @param  array<int, array{username: string, password: string}>  $credentials
     * @return array<int, array{username: string, password: string}>
     */
    private function hashCredentials(array $credentials): array
    {
        return array_map(fn (array $credential): array => [
            'username' => $credential['username'],
            'password' => Hash::driver('bcrypt')->make($credential['password'], ['rounds' => self::PASSWORD_HASH_COST]),
        ], $credentials);
    }
}
