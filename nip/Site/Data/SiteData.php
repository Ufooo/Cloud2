<?php

namespace Nip\Site\Data;

use Nip\Server\Enums\IdentityColor;
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
        public ?int $provisioningStep,
        /** @var SiteProvisioningStepData[]|null */
        public ?array $provisioningSteps,
        public ?string $deployStatus,
        public ?string $displayableDeployStatus,
        public ?string $deployStatusBadgeVariant,
        public string $user,
        public string $rootDirectory,
        public string $webDirectory,
        public string $fullPath,
        public string $webPath,
        public string $url,
        public ?string $phpVersionLabel,
        public ?string $packageManager,
        public ?string $buildCommand,
        public ?string $repository,
        public ?string $branch,
        public ?string $displayableRepository,
        public ?string $sourceControlProvider,
        public ?IdentityColor $avatarColor,
        public ?string $notes,
        public ?string $lastDeployedAt,
        public ?string $lastDeployedAtHuman,
        public ?string $createdAt,
        // Deployment settings
        public ?string $deployScript,
        public bool $pushToDeploy,
        public bool $autoSource,
        public ?string $deployHookUrl,
        public int $deploymentRetention,
        public bool $zeroDowntime,
        public ?string $healthcheckEndpoint,
        public ?string $deployKey,
        /** @var string[]|null */
        public ?array $detectedPackages,
        public ?string $detectedVersion,
        /** @var DetectedPackageData[]|null */
        public ?array $packageDetails,
        /** @var array<string, bool>|null */
        public ?array $packages,
        public SitePermissionsData $can,
        // Database info
        public ?int $databaseId,
        public ?string $databaseName,
        public ?int $databaseUserId,
        public ?string $databaseUserName,
    ) {}

    public static function fromModel(Site $site): self
    {
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
            provisioningStep: $site->provisioning_step?->value,
            provisioningSteps: self::getProvisioningStepsData($site),
            deployStatus: $site->deploy_status?->value,
            displayableDeployStatus: $site->deploy_status?->label(),
            deployStatusBadgeVariant: $site->deploy_status?->badgeVariant(),
            user: $site->user,
            rootDirectory: $site->root_directory,
            webDirectory: $site->web_directory,
            fullPath: $site->getFullPath(),
            webPath: $site->getWebPath(),
            url: $site->getUrl(),
            phpVersionLabel: $site->php_version?->version(),
            packageManager: $site->package_manager?->value,
            buildCommand: $site->build_command,
            repository: $site->repository,
            branch: $site->branch,
            displayableRepository: $site->getDisplayableRepository(),
            sourceControlProvider: $site->relationLoaded('sourceControl') ? $site->sourceControl?->provider?->value : null,
            avatarColor: $site->avatar_color,
            notes: $site->notes,
            lastDeployedAt: $site->last_deployed_at?->toISOString(),
            lastDeployedAtHuman: $site->last_deployed_at?->diffForHumans(),
            createdAt: $site->created_at?->toISOString(),
            deployScript: $site->deploy_script,
            pushToDeploy: $site->push_to_deploy ?? false,
            autoSource: $site->auto_source ?? false,
            deployHookUrl: $site->getDeployHookUrl(),
            deploymentRetention: $site->deployment_retention ?? 5,
            zeroDowntime: $site->zero_downtime ?? false,
            healthcheckEndpoint: $site->healthcheck_endpoint,
            deployKey: $site->deploy_key,
            detectedPackages: $site->detected_packages,
            detectedVersion: self::extractVersionFromPackages($site->detected_packages),
            packageDetails: self::getPackageDetailsData($site),
            packages: $site->packages,
            can: $site->getPermissions(),
            databaseId: $site->database_id,
            databaseName: $site->relationLoaded('database') ? $site->database?->name : null,
            databaseUserId: $site->database_user_id,
            databaseUserName: $site->relationLoaded('databaseUser') ? $site->databaseUser?->username : null,
        );
    }

    /**
     * @return array<SiteProvisioningStepData>|null
     */
    private static function getProvisioningStepsData(Site $site): ?array
    {
        return $site->status === SiteStatus::Installing
            ? $site->getProvisioningSteps()
            : null;
    }

    /**
     * @return array<DetectedPackageData>|null
     */
    private static function getPackageDetailsData(Site $site): ?array
    {
        return $site->detected_packages
            ? DetectedPackageData::fromPackageValues($site->detected_packages, $site)
            : null;
    }

    /**
     * Extract version from detected_packages array.
     * Looks for entries like 'version:6.8.3' and returns '6.8.3'.
     *
     * @param  array<string>|null  $packages
     */
    private static function extractVersionFromPackages(?array $packages): ?string
    {
        if (! $packages) {
            return null;
        }

        foreach ($packages as $package) {
            if (str_starts_with($package, 'version:')) {
                return substr($package, 8);
            }
        }

        return null;
    }
}
