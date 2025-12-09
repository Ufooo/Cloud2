<?php

namespace Nip\Server\Data;

use Nip\Server\Enums\ServerType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ServerTypeOptionData extends Data
{
    public function __construct(
        public ServerType $value,
        public string $label,
        public string $description,
    ) {}
}
