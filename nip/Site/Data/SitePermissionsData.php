<?php

namespace Nip\Site\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SitePermissionsData extends Data
{
    public function __construct(
        public bool $update,
        public bool $delete,
        public bool $deploy,
    ) {}
}
