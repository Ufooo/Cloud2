<?php

namespace Nip\Domain\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CertificateData extends Data
{
    public function __construct(
        public string $id,
        public string $siteId,
        public string $type,
        public string $displayableType,
        public string $status,
        public string $displayableStatus,
        public string $statusBadgeVariant,
        /** @var array<int, string> */
        public array $domains,
        public bool $active,
        public ?string $path,
        public ?string $issuedAt,
        public ?string $issuedAtHuman,
        public ?string $expiresAt,
        public ?string $expiresAtHuman,
        public bool $isExpiringSoon,
        public ?int $daysUntilExpiry,
        public ?string $createdAt,
        public CertificatePermissionsData $can,
    ) {}
}
