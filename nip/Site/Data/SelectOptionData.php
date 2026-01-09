<?php

namespace Nip\Site\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SelectOptionData extends Data
{
    public function __construct(
        public string|int $value,
        public string $label,
    ) {}
}
