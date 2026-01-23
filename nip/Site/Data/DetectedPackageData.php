<?php

namespace Nip\Site\Data;

use Nip\Site\Enums\DetectedPackage;
use Nip\Site\Models\Site;
use Nip\Site\Services\InertiaSSRService;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DetectedPackageData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
        public string $description,
        public ?string $version,
        public bool $hasEnableAction,
        public ?string $enableActionLabel,
        public ?string $disableActionLabel,
        public bool $isEnabled,
    ) {}

    public static function fromEnum(DetectedPackage $package, bool $isEnabled = false, ?string $version = null): self
    {
        return new self(
            value: $package->value,
            label: $package->label(),
            description: $package->description(),
            version: $version,
            hasEnableAction: $package->hasEnableAction(),
            enableActionLabel: $package->enableActionLabel(),
            disableActionLabel: $package->disableActionLabel(),
            isEnabled: $isEnabled,
        );
    }

    /**
     * @param  array<string, string>|array<string>  $packageValues
     * @return array<self>
     */
    public static function fromPackageValues(array $packageValues, ?Site $site = null): array
    {
        $details = [];
        $ssrService = $site ? app(InertiaSSRService::class) : null;

        foreach (DetectedPackage::cases() as $package) {
            // Support both old format (indexed array) and new format (associative with versions)
            $isInstalled = isset($packageValues[$package->value])
                || in_array($package->value, $packageValues, true);

            if ($isInstalled) {
                $version = $packageValues[$package->value] ?? null;
                $isEnabled = self::checkPackageEnabled($package, $site, $ssrService);
                $details[] = self::fromEnum($package, $isEnabled, is_string($version) ? $version : null);
            }
        }

        return $details;
    }

    protected static function checkPackageEnabled(
        DetectedPackage $package,
        ?Site $site,
        ?InertiaSSRService $ssrService
    ): bool {
        if (! $site) {
            return false;
        }

        return match ($package) {
            DetectedPackage::Inertia => $ssrService?->isEnabled($site) ?? false,
            DetectedPackage::Horizon => $site->backgroundProcesses()
                ->where('command', 'like', '%horizon%')
                ->exists(),
            DetectedPackage::Reverb => $site->backgroundProcesses()
                ->where('command', 'like', '%reverb%')
                ->exists(),
            DetectedPackage::Octane => $site->backgroundProcesses()
                ->where('command', 'like', '%octane%')
                ->exists(),
            default => false,
        };
    }
}
