<?php

namespace Nip\SecurityMonitor\Services;

use Nip\SecurityMonitor\Enums\GitChangeType;
use RuntimeException;

class GitStatusParser
{
    /**
     * Parse git status JSON output from SSH script.
     *
     * @param  string  $jsonOutput  Raw JSON from git-scan script
     * @return array{sites: array<array{path: string, changes?: array<array{status: string, type: string, file: string}>, error?: string}>}
     *
     * @throws RuntimeException
     */
    public function parse(string $jsonOutput): array
    {
        $data = json_decode($jsonOutput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON output from git scan: '.json_last_error_msg());
        }

        return $data;
    }

    /**
     * Extract changes for a specific site path.
     *
     * @param  array{sites: array<array{path: string, changes?: array<array{status: string, type: string, file: string}>, error?: string}>}  $parsedData
     * @return array<array{status: string, type: string, file: string}>
     */
    public function getChangesForSite(array $parsedData, string $sitePath): array
    {
        foreach ($parsedData['sites'] ?? [] as $site) {
            if ($site['path'] === $sitePath) {
                return $site['changes'] ?? [];
            }
        }

        return [];
    }

    /**
     * Check if site has an error.
     *
     * @param  array{sites: array<array{path: string, changes?: array<array{status: string, type: string, file: string}>, error?: string}>}  $parsedData
     */
    public function getSiteError(array $parsedData, string $sitePath): ?string
    {
        foreach ($parsedData['sites'] ?? [] as $site) {
            if ($site['path'] === $sitePath) {
                return $site['error'] ?? null;
            }
        }

        return null;
    }

    /**
     * Map git status code to GitChangeType enum value.
     */
    public function mapStatusToChangeType(string $status): GitChangeType
    {
        return match (trim($status)) {
            '??' => GitChangeType::Untracked,
            ' D', 'D ', 'DD' => GitChangeType::Deleted,
            ' M', 'M ', 'MM' => GitChangeType::Modified,
            ' A', 'A ', 'AA' => GitChangeType::Added,
            ' R', 'R ' => GitChangeType::Renamed,
            ' C', 'C ' => GitChangeType::Copied,
            default => GitChangeType::Unknown,
        };
    }
}
