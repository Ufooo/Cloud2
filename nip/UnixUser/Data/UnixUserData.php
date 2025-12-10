<?php

namespace Nip\UnixUser\Data;

use Nip\UnixUser\Enums\UserStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UnixUserData extends Data
{
    public function __construct(
        public int $id,
        public string $username,
        public UserStatus $status,
        public string $displayableStatus,
    ) {}
}
