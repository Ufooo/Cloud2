<?php

namespace Nip\Server\Data;

use Nip\Server\Enums\ProvisionScriptStatus;
use Nip\Server\Models\ProvisionScript;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProvisionScriptData extends Data
{
    public function __construct(
        public int $id,
        public int $serverId,
        public ?string $serverName,
        public ?string $serverSlug,
        public string $filename,
        public ?string $resourceType,
        public ?int $resourceId,
        public ?string $displayableName,
        public ProvisionScriptStatus $status,
        public ?int $exitCode,
        public ?string $errorMessage,
        public ?string $output,
        public ?int $duration,
        public ?string $executedAt,
        public string $createdAt,
    ) {}

    public static function fromModel(ProvisionScript $script): self
    {
        return new self(
            id: $script->id,
            serverId: $script->server_id,
            serverName: $script->server?->name,
            serverSlug: $script->server?->slug,
            filename: $script->filename,
            resourceType: $script->resource_type,
            resourceId: $script->resource_id,
            displayableName: $script->displayable_name,
            status: $script->status,
            exitCode: $script->exit_code,
            errorMessage: $script->error_message,
            output: $script->output,
            duration: $script->duration,
            executedAt: $script->executed_at?->toISOString(),
            createdAt: $script->created_at->toISOString(),
        );
    }
}
