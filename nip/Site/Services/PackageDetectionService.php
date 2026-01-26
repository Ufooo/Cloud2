<?php

namespace Nip\Site\Services;

use Exception;
use Nip\Server\Services\SSH\SSHService;
use Nip\Site\Enums\DetectedPackage;
use Nip\Site\Enums\SitePackage;
use Nip\Site\Enums\SiteType;
use Nip\Site\Models\Site;

class PackageDetectionService
{
    public function __construct(
        protected SSHService $ssh,
    ) {}

    /**
     * Detect packages from composer.lock on the remote server.
     *
     * @return array<string, string> Package name => version
     */
    public function detectPackages(Site $site): array
    {
        $composerLockPath = $site->getApplicationPath().'/composer.lock';

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
     * Parse composer.lock content and extract known packages with versions.
     *
     * @return array<string, string>
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
            $version = $package['version'] ?? null;

            if ($packageName && isset($packageMap[$packageName])) {
                $detectedPackages[$packageMap[$packageName]] = $version;
            }
        }

        // Also check packages-dev for dev dependencies
        if (isset($data['packages-dev'])) {
            foreach ($data['packages-dev'] as $package) {
                $packageName = $package['name'] ?? null;
                $version = $package['version'] ?? null;

                if ($packageName && isset($packageMap[$packageName]) && ! isset($detectedPackages[$packageMap[$packageName]])) {
                    $detectedPackages[$packageMap[$packageName]] = $version;
                }
            }
        }

        return $detectedPackages;
    }

    /**
     * Build site packages array from detected packages.
     * Maps detected packages to SitePackage enum values with enabled state.
     *
     * @param  array<string, string>  $detectedPackages
     * @return array<string, bool>
     */
    protected function buildSitePackages(array $detectedPackages, Site $site): array
    {
        $sitePackages = [];

        // Detect composer packages
        foreach (SitePackage::detectable() as $sitePackage) {
            if (isset($detectedPackages[$sitePackage->value])) {
                // For now, all detected packages are marked as enabled (true)
                // In the future, we could check if daemons are actually running
                $sitePackages[$sitePackage->value] = true;
            }
        }

        // Detect features for Laravel sites (only add if active)
        if (isset($detectedPackages['laravel'])) {
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
     * Detect WordPress version from the remote server.
     *
     * @return array<string>
     */
    public function detectWordPressVersion(Site $site): array
    {
        if ($site->type !== SiteType::WordPress) {
            return [];
        }

        $applicationPath = $site->getApplicationPath();
        $versionFile = "{$applicationPath}/wp-includes/version.php";

        try {
            $this->ssh->connect($site->server, $site->user);
            $content = $this->ssh->getFileContent($versionFile);
            $this->ssh->disconnect();

            if (! $content) {
                return [];
            }

            $version = $this->parseWordPressVersion($content);
            $packages = $version ? ["version:{$version}"] : [];

            $site->update([
                'detected_packages' => $packages,
            ]);

            return $packages;
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Parse WordPress version from version.php content.
     */
    protected function parseWordPressVersion(string $content): ?string
    {
        if (preg_match('/\$wp_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get package details with metadata.
     *
     * @return array<array{
     *     value: string,
     *     label: string,
     *     description: string,
     *     version: ?string,
     *     hasEnableAction: bool,
     *     enableActionLabel: ?string
     * }>
     */
    public function getPackageDetails(Site $site): array
    {
        $detectedPackages = $site->detected_packages ?? [];
        $packages = [];

        foreach (DetectedPackage::cases() as $package) {
            // Support both old format (indexed array) and new format (associative with versions)
            $isInstalled = isset($detectedPackages[$package->value])
                || in_array($package->value, $detectedPackages, true);

            if ($isInstalled) {
                $version = $detectedPackages[$package->value] ?? null;

                $packages[] = [
                    'value' => $package->value,
                    'label' => $package->label(),
                    'description' => $package->description(),
                    'version' => is_string($version) ? $version : null,
                    'hasEnableAction' => $package->hasEnableAction(),
                    'enableActionLabel' => $package->enableActionLabel(),
                ];
            }
        }

        return $packages;
    }
}
