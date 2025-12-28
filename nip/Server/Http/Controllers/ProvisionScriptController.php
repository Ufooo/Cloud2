<?php

namespace Nip\Server\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Nip\Server\Enums\ProvisionScriptStatus;
use Nip\Server\Http\Resources\ProvisionScriptResource;
use Nip\Server\Models\ProvisionScript;
use Nip\Server\Models\Server;

class ProvisionScriptController extends Controller
{
    public function failed(Request $request): JsonResponse
    {
        $query = ProvisionScript::query()
            ->with('server')
            ->where('status', ProvisionScriptStatus::Failed)
            ->orderBy('created_at', 'desc')
            ->limit(10);

        if ($request->has('types')) {
            $types = explode(',', $request->get('types'));
            $query->whereIn('resource_type', $types);
        }

        return response()->json(ProvisionScriptResource::collection($query->get()));
    }

    public function failedForServer(Server $server, Request $request): JsonResponse
    {
        Gate::authorize('view', $server);

        $query = ProvisionScript::query()
            ->where('server_id', $server->id)
            ->where('status', ProvisionScriptStatus::Failed)
            ->orderBy('created_at', 'desc')
            ->limit(10);

        if ($request->has('types')) {
            $types = explode(',', $request->get('types'));
            $query->whereIn('resource_type', $types);
        }

        return response()->json(ProvisionScriptResource::collection($query->get()));
    }

    public function show(ProvisionScript $provisionScript): JsonResponse
    {
        Gate::authorize('view', $provisionScript->server);

        return response()->json(ProvisionScriptResource::make($provisionScript));
    }

    public function dismiss(ProvisionScript $provisionScript): JsonResponse
    {
        Gate::authorize('view', $provisionScript->server);

        $provisionScript->update([
            'status' => ProvisionScriptStatus::Completed,
        ]);

        return response()->json(['success' => true]);
    }
}
