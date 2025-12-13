<?php

namespace Nip\Domain\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DomainRecordPermissionsData extends Data
{
    public function __construct(
        public bool $update,
        public bool $delete,
        public bool $makePrimary,
    ) {}
}
