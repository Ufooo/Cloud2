<?php

namespace Nip\Network\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsServerPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
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
use Nip\Server\Data\ServerData;
use Nip\Server\Models\Server;

class NetworkController extends Controller
{
    use LoadsServerPermissions;

    public function index(Server $server): Response
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
        ]);
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
