<?php

namespace Nip\Site\Services;

use Exception;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Enums\DetectedPackage;
use Nip\Site\Enums\SitePackage;
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
            $sitePackages = $this->buildSitePackages($packages, $site);

            $site->update([
                'detected_packages' => $packages,
                'packages' => $sitePackages,
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
     * Build site packages array from detected packages.
     * Maps detected packages to SitePackage enum values with enabled state.
     *
     * @param  array<string>  $detectedPackages
     * @return array<string, bool>
     */
    protected function buildSitePackages(array $detectedPackages, Site $site): array
    {
        $sitePackages = [];

        // Detect composer packages
        foreach (SitePackage::detectable() as $sitePackage) {
            if (in_array($sitePackage->value, $detectedPackages, true)) {
                // For now, all detected packages are marked as enabled (true)
                // In the future, we could check if daemons are actually running
                $sitePackages[$sitePackage->value] = true;
            }
        }

        // Detect features for Laravel sites (only add if active)
        if (in_array('laravel', $detectedPackages, true)) {
            // Scheduler: only show badge if has active jobs
            if ($site->scheduledJobs()->exists()) {
                $sitePackages[SitePackage::Scheduler->value] = true;
            }

            // Maintenance mode would require SSH check for storage/framework/down
            // For now, we don't detect it automatically
        }

        return $sitePackages;
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
