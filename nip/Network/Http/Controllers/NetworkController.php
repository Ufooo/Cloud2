<?php

namespace Nip\Network\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Nip\Network\Enums\RuleStatus;
use Nip\Network\Enums\RuleType;
use Nip\Network\Http\Requests\StoreFirewallRuleRequest;
use Nip\Network\Http\Resources\FirewallRuleResource;
use Nip\Network\Jobs\RemoveFirewallRuleJob;
use Nip\Network\Jobs\SyncFirewallRuleJob;
use Nip\Network\Models\FirewallRule;
use Nip\Network\Services\Fail2banService;
use Nip\Server\Data\ServerData;
use Nip\Server\Models\Server;

class NetworkController extends Controller
{
    use LoadsServerPermissions;

    public function index(Server $server, Fail2banService $fail2ban): Response
    {
        Gate::authorize('view', $server);

        $this->loadServerPermissions($server);

        $rules = $server->firewallRules()
            ->orderBy('name')
            ->paginate(10);

        return Inertia::render('servers/Network', [
            'server' => ServerData::from($server),
            'rules' => FirewallRuleResource::collection($rules),
            'ruleTypes' => RuleType::options(),
            'bannedIps' => Inertia::defer(fn () => $fail2ban->getBannedIps($server)),
        ]);
    }

    public function unban(Request $request, Server $server, Fail2banService $fail2ban): RedirectResponse
    {
        Gate::authorize('update', $server);

        $validated = $request->validate([
            'jail' => ['required', 'string', 'regex:/^[a-z][a-z0-9\-]*$/'],
            'ip' => ['required', 'ip'],
        ]);

        $success = $fail2ban->unbanIp($server, $validated['jail'], $validated['ip']);

        if (! $success) {
            return redirect()
                ->route('servers.network', $server)
                ->with('error', "Failed to unban IP {$validated['ip']}.");
        }

        return redirect()
            ->route('servers.network', $server)
            ->with('success', "IP {$validated['ip']} has been unbanned.");
    }

    public function geoip(Request $request, Server $server, Fail2banService $fail2ban): JsonResponse
    {
        Gate::authorize('view', $server);

        $validated = $request->validate([
            'ips' => ['required', 'array', 'max:500'],
            'ips.*' => ['required', 'ip'],
        ]);

        return response()->json($fail2ban->resolveGeoIp($validated['ips']));
    }

    public function store(StoreFirewallRuleRequest $request, Server $server): RedirectResponse
    {
        Gate::authorize('update', $server);

        $rule = $server->firewallRules()->create([
            ...$request->validated(),
            'status' => RuleStatus::Installing,
        ]);

        SyncFirewallRuleJob::dispatch($rule);

        return redirect()
            ->route('servers.network', $server)->with('success', 'Firewall rule created. Installing on server...');
    }

    public function destroy(Server $server, FirewallRule $rule): RedirectResponse
    {
        Gate::authorize('update', $server);

        abort_unless($rule->server_id === $server->id, 403);

        $rule->update(['status' => RuleStatus::Deleting]);

        RemoveFirewallRuleJob::dispatch($rule);

        return redirect()
            ->route('servers.network', $server)->with('success', 'Removing firewall rule from server...');
    }
}
