<?php

namespace Nip\Site\Data;

use Nip\Site\Enums\DetectedPackage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DetectedPackageData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
        public string $description,
        public bool $hasEnableAction,
        public ?string $enableActionLabel,
    ) {}

    public static function fromEnum(DetectedPackage $package): self
    {
        return new self(
            value: $package->value,
            label: $package->label(),
            description: $package->description(),
            hasEnableAction: $package->hasEnableAction(),
            enableActionLabel: $package->enableActionLabel(),
        );
    }

    /**
     * @param  array<string>  $packageValues
     * @return array<self>
     */
    public static function fromPackageValues(array $packageValues): array
    {
        $details = [];

        foreach (DetectedPackage::cases() as $package) {
            if (in_array($package->value, $packageValues, true)) {
                $details[] = self::fromEnum($package);
            }
        }

        return $details;
    }
}
