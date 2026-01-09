<?php

namespace Nip\Server\Data;

use Nip\Server\Enums\DatabaseType;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\ServerProvider;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Enums\ServerType;
use Nip\Server\Enums\Timezone;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
class ServerData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ServerProvider $provider,
        public ?string $providerServerId,
        public ServerType $type,
        public ?string $displayableType,
        public ServerStatus $status,
        public ?string $provisioningCommand,
        public int $provisionStep,
        /** @var ProvisioningStepData[]|null */
        public ?array $provisioningSteps,
        public ?string $ipAddress,
        public ?string $privateIpAddress,
        public string $sshPort,
        public string $phpVersion,
        #[MapInputName('php_version_string')]
        public ?string $phpVersionLabel,
        public ?DatabaseType $databaseType,
        public ?string $dbStatus,
        public ?string $ubuntuVersion,
        public Timezone $timezone,
        public ?string $notes,
        public IdentityColor $avatarColor,
        public ?array $services,
        public ?string $displayableProvider,
        public ?string $displayableDatabaseType,
        public ?string $cloudProviderUrl,
        public bool $isReady,
        public ?string $gitPublicKey,
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?\DateTimeInterface $lastConnectedAt,
        #[WithCast(DateTimeInterfaceCast::class)]
        public \DateTimeInterface $createdAt,
        #[WithCast(DateTimeInterfaceCast::class)]
        public \DateTimeInterface $updatedAt,
        public ServerPermissionsData $can,
    ) {}
}
