<?php

namespace Nip\Php\Data;

use Nip\Php\Enums\PhpVersionStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PhpVersionData extends Data
{
    public function __construct(
        public int $id,
        public string $version,
        #[MapInputName('is_cli_default')]
        public bool $isCliDefault,
        #[MapInputName('is_site_default')]
        public bool $isSiteDefault,
        public PhpVersionStatus $status,
        #[MapInputName('created_at')]
        public ?string $createdAt,
    ) {}
}
