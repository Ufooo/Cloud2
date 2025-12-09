<?php

namespace Nip\Server\Data;

use Nip\Server\Enums\ServerProvider;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ServerProviderOptionData extends Data
{
    public function __construct(
        public ServerProvider $value,
        public string $label,
    ) {}
}
