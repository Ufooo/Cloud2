<?php

namespace Nip\Deployment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Deployment\Events\DeploymentUpdated;
use Nip\Deployment\Models\Deployment;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Events\SiteStatusUpdated;

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

        DeploymentUpdated::dispatch($deployment);
        SiteStatusUpdated::dispatch($site);

        return response()->json(['success' => true]);
    }
}
