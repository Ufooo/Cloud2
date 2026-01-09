<?php

namespace Nip\Site\Data;

use Nip\Database\Models\Database;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DatabaseOptionData extends Data
{
    public function __construct(
        public int $value,
        public string $label,
        /** @var array<int, int> */
        public array $userIds,
    ) {}

    public static function fromModel(Database $database): self
    {
        return new self(
            value: $database->id,
            label: $database->name,
            userIds: $database->relationLoaded('users')
                ? $database->users->pluck('id')->all()
                : [],
        );
    }
}
