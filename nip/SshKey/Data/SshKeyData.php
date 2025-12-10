<?php

namespace Nip\SshKey\Data;

use Nip\UnixUser\Data\UnixUserData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SshKeyData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $fingerprint,
        #[MapInputName('created_at')]
        public string $createdAt,
        #[MapInputName('unix_user')]
        public ?UnixUserData $unixUser = null,
    ) {}
}
