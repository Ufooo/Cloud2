<?php

namespace Nip\Site\Services;

use Exception;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Enums\DetectedPackage;
use Nip\Site\Models\Site;

class PackageDetectionService
{
    public function __construct(
        protected SSHService $ssh,
    ) {}

    /**
     * Detect packages from composer.lock on the remote server.
     *
     * @return array<string>
     */
    public function detectPackages(Site $site): array
    {
        $composerLockPath = $site->getCurrentPath().'/composer.lock';

        try {
            $this->ssh->connect($site->server, $site->user);
            $content = $this->ssh->getFileContent($composerLockPath);
            $this->ssh->disconnect();

            if (! $content) {
                return [];
            }

            $packages = $this->parseComposerLock($content);

            $site->update([
                'detected_packages' => $packages,
            ]);

            return $packages;
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Parse composer.lock content and extract known packages.
     *
     * @return array<string>
     */
    protected function parseComposerLock(string $content): array
    {
        $data = json_decode($content, true);

        if (! $data || ! isset($data['packages'])) {
            return [];
        }

        $packageMap = DetectedPackage::composerPackageMap();
        $detectedPackages = [];

        foreach ($data['packages'] as $package) {
            $packageName = $package['name'] ?? null;

            if ($packageName && isset($packageMap[$packageName])) {
                $detectedPackages[] = $packageMap[$packageName];
            }
        }

        // Also check packages-dev for dev dependencies
        if (isset($data['packages-dev'])) {
            foreach ($data['packages-dev'] as $package) {
                $packageName = $package['name'] ?? null;

                if ($packageName && isset($packageMap[$packageName])) {
                    $detectedPackages[] = $packageMap[$packageName];
                }
            }
        }

        return array_unique($detectedPackages);
    }

    /**
     * Get package details with metadata.
     *
     * @return array<array{
     *     value: string,
     *     label: string,
     *     description: string,
     *     hasEnableAction: bool,
     *     enableActionLabel: ?string
     * }>
     */
    public function getPackageDetails(Site $site): array
    {
        $detectedPackages = $site->detected_packages ?? [];
        $packages = [];

        foreach (DetectedPackage::cases() as $package) {
            $isInstalled = in_array($package->value, $detectedPackages, true);

            if ($isInstalled) {
                $packages[] = [
                    'value' => $package->value,
                    'label' => $package->label(),
                    'description' => $package->description(),
                    'hasEnableAction' => $package->hasEnableAction(),
                    'enableActionLabel' => $package->enableActionLabel(),
                ];
            }
        }

        return $packages;
    }
}
