<?php

namespace Nip\Server\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ServerPermissionsData extends Data
{
    public function __construct(
        public bool $view,
        public bool $update,
        public bool $delete,
    ) {}
}
