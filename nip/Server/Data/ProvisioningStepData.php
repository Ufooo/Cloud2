<?php

namespace Nip\Server\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProvisioningStepData extends Data
{
    public function __construct(
        public int $value,
        public string $label,
        public string $description,
    ) {}
}
