<?php

namespace Nip\Site\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nip\Php\Enums\PhpVersion;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Models\Site;

/**
 * @mixin Site
 */
class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canUpdate = $request->user()?->can('update', $this->server);
        $isInstalled = $this->status === SiteStatus::Installed;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'serverId' => $this->server_id,
            'serverName' => $this->whenLoaded('server', fn () => $this->server->name),
            'serverSlug' => $this->whenLoaded('server', fn () => $this->server->slug),
            'domain' => $this->domain,
            'type' => $this->type?->value,
            'displayableType' => $this->type?->label(),
            'status' => $this->status?->value,
            'displayableStatus' => $this->status?->label(),
            'statusBadgeVariant' => $this->status?->badgeVariant(),
            'deployStatus' => $this->deploy_status?->value,
            'displayableDeployStatus' => $this->deploy_status?->label(),
            'deployStatusBadgeVariant' => $this->deploy_status?->badgeVariant(),
            'user' => $this->user,
            'rootDirectory' => $this->root_directory,
            'webDirectory' => $this->web_directory,
            'fullPath' => $this->getFullPath(),
            'webPath' => $this->getWebPath(),
            'url' => $this->getUrl(),
            'phpVersion' => $this->php_version ? PhpVersion::tryFrom($this->php_version)?->label() : null,
            'packageManager' => $this->package_manager?->value,
            'buildCommand' => $this->build_command,
            'repository' => $this->repository,
            'branch' => $this->branch,
            'displayableRepository' => $this->getDisplayableRepository(),
            'isIsolated' => $this->is_isolated,
            'avatarColor' => $this->avatar_color?->value,
            'notes' => $this->notes,
            'lastDeployedAt' => $this->last_deployed_at?->toISOString(),
            'lastDeployedAtHuman' => $this->last_deployed_at?->diffForHumans(),
            'createdAt' => $this->created_at?->toISOString(),
            'can' => [
                'update' => $canUpdate && $isInstalled,
                'delete' => $canUpdate && $this->status !== SiteStatus::Installing,
                'deploy' => $canUpdate && $isInstalled && $this->repository,
            ],
        ];
    }
}
