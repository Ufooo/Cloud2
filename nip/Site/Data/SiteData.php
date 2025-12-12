<?php

namespace Nip\Site\Data;

use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\PhpVersion;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Models\Site;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SiteData extends Data
{
    public function __construct(
        public string $id,
        public string $slug,
        public int $serverId,
        public ?string $serverName,
        public ?string $serverSlug,
        public string $domain,
        public ?string $type,
        public ?string $displayableType,
        public ?string $status,
        public ?string $displayableStatus,
        public ?string $statusBadgeVariant,
        public ?string $deployStatus,
        public ?string $displayableDeployStatus,
        public ?string $deployStatusBadgeVariant,
        public string $user,
        public string $rootDirectory,
        public string $webDirectory,
        public string $fullPath,
        public string $webPath,
        public string $url,
        public ?string $phpVersion,
        public ?string $packageManager,
        public ?string $buildCommand,
        public ?string $repository,
        public ?string $branch,
        public ?string $displayableRepository,
        public bool $isIsolated,
        public ?IdentityColor $avatarColor,
        public ?string $notes,
        public ?string $lastDeployedAt,
        public ?string $lastDeployedAtHuman,
        public ?string $createdAt,
        public SitePermissionsData $can,
    ) {}

    public static function fromModel(Site $site): self
    {
        $canUpdate = request()->user()?->can('update', $site->server);
        $isInstalled = $site->status === SiteStatus::Installed;

        return new self(
            id: $site->id,
            slug: $site->slug,
            serverId: $site->server_id,
            serverName: $site->relationLoaded('server') ? $site->server->name : null,
            serverSlug: $site->relationLoaded('server') ? $site->server->slug : null,
            domain: $site->domain,
            type: $site->type?->value,
            displayableType: $site->type?->label(),
            status: $site->status?->value,
            displayableStatus: $site->status?->label(),
            statusBadgeVariant: $site->status?->badgeVariant(),
            deployStatus: $site->deploy_status?->value,
            displayableDeployStatus: $site->deploy_status?->label(),
            deployStatusBadgeVariant: $site->deploy_status?->badgeVariant(),
            user: $site->user,
            rootDirectory: $site->root_directory,
            webDirectory: $site->web_directory,
            fullPath: $site->getFullPath(),
            webPath: $site->getWebPath(),
            url: $site->getUrl(),
            phpVersion: $site->php_version ? PhpVersion::tryFrom($site->php_version)?->label() : null,
            packageManager: $site->package_manager?->value,
            buildCommand: $site->build_command,
            repository: $site->repository,
            branch: $site->branch,
            displayableRepository: $site->getDisplayableRepository(),
            isIsolated: $site->is_isolated,
            avatarColor: $site->avatar_color,
            notes: $site->notes,
            lastDeployedAt: $site->last_deployed_at?->toISOString(),
            lastDeployedAtHuman: $site->last_deployed_at?->diffForHumans(),
            createdAt: $site->created_at?->toISOString(),
            can: new SitePermissionsData(
                update: $canUpdate && $isInstalled,
                delete: $canUpdate && $site->status !== SiteStatus::Installing,
                deploy: $canUpdate && $isInstalled && (bool) $site->repository,
            ),
        );
    }
}
