<?php

namespace Nip\Deployment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Deployment\Events\DeploymentUpdated;
use Nip\Deployment\Models\Deployment;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Events\SiteStatusUpdated;
use Throwable;

class DeploymentCallbackController
{
    public function __invoke(string $token): JsonResponse
    {
        $deployment = Deployment::where('callback_token', $token)
            ->where('status', DeploymentStatus::Deploying)
            ->first();

        if (! $deployment) {
            return response()->json(['error' => 'Invalid or expired callback token'], 404);
        }

        $deployment->update([
            'status' => DeploymentStatus::Finished,
            'callback_token' => null,
            'ended_at' => now(),
        ]);

        $site = $deployment->site;
        $site->update([
            'deploy_status' => DeployStatus::Deployed,
            'last_deployed_at' => now(),
        ]);

        // Safely broadcast - Reverb may be unavailable during self-deployment
        try {
            DeploymentUpdated::dispatch($deployment);
            SiteStatusUpdated::dispatch($site);
        } catch (Throwable $e) {
            Log::warning('Failed to broadcast deployment callback update (Reverb may be unavailable)', [
                'deployment_id' => $deployment->id,
                'site_id' => $site->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
