<?php

namespace Nip\Site\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SiteProvisioningStepData extends Data
{
    public function __construct(
        public int $value,
        public string $label,
        public string $description,
    ) {}
}
