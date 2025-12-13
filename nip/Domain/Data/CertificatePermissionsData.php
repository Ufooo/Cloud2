<?php

namespace Nip\Domain\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CertificatePermissionsData extends Data
{
    public function __construct(
        public bool $delete,
        public bool $activate,
        public bool $deactivate,
        public bool $renew,
    ) {}
}
