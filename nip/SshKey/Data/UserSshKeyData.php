<?php

namespace Nip\SshKey\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserSshKeyData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $fingerprint,
        public ?string $userName = null,
        public ?string $createdAt = null,
    ) {}
}
