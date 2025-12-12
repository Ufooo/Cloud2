<?php

namespace Nip\Site\Data;

use Nip\Server\Enums\IdentityColor;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
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
}
