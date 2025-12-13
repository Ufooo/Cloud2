<?php

namespace Nip\Domain\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DomainRecordData extends Data
{
    public function __construct(
        public string $id,
        public string $siteId,
        public ?string $certificateId,
        public bool $isSecured,
        public ?string $certificateType,
        public string $name,
        public string $type,
        public string $displayableType,
        public string $status,
        public string $displayableStatus,
        public string $statusBadgeVariant,
        public string $wwwRedirectType,
        public string $wwwRedirectTypeLabel,
        public bool $allowWildcard,
        public bool $isPrimary,
        public string $url,
        public ?string $createdAt,
        public DomainRecordPermissionsData $can,
    ) {}
}
