<?php

namespace Nip\Server\Data;

use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\ServerProvider;
use Nip\Server\Enums\ServerType;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
class ServerCreateData extends Data
{
    public function __construct(
        public string $name,
        public ServerProvider $provider,
        public ServerType $type,
        public ?string $ipAddress = null,
        public ?string $privateIpAddress = null,
        public string $sshPort = '22',
        public string $phpVersion = 'php83',
        public ?string $databaseType = null,
        public ?string $ubuntuVersion = null,
        public string $timezone = 'UTC',
        public ?string $notes = null,
        public IdentityColor $avatarColor = IdentityColor::Blue,
        public ?array $services = null,
    ) {}
}
