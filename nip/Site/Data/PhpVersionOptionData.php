<?php

namespace Nip\Site\Data;

use Illuminate\Support\Collection;
use Nip\Php\Enums\PhpVersion;
use Nip\Php\Models\PhpVersion as PhpVersionModel;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PhpVersionOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
        public bool $isDefault,
    ) {}

    public static function fromModel(PhpVersionModel $phpVersion): self
    {
        return new self(
            value: $phpVersion->version,
            label: PhpVersion::fromVersion($phpVersion->version)?->label() ?? "PHP {$phpVersion->version}",
            isDefault: $phpVersion->is_site_default,
        );
    }

    /**
     * Create select options from a collection of PHP versions (without isDefault).
     *
     * @param  Collection<int, PhpVersionModel>  $phpVersions
     * @return array<int, SelectOptionData>
     */
    public static function toSelectOptions(Collection $phpVersions): array
    {
        return $phpVersions->map(fn (PhpVersionModel $pv) => new SelectOptionData(
            value: PhpVersion::fromVersion($pv->version)?->value ?? $pv->version,
            label: PhpVersion::fromVersion($pv->version)?->label() ?? "PHP {$pv->version}",
        ))->values()->all();
    }
}
