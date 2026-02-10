<?php

namespace Nip\Network\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BannedIpData extends Data
{
    public function __construct(
        public string $ip,
        public string $jail,
        public string $bannedAt,
        public int $banDuration,
        public int $banCount,
        public ?string $expiresAt,
        public string $bannedSinceHuman,
        public string $remainingTimeHuman,
        public bool $isPermanent,
        public ?string $country = null,
        public ?string $countryCode = null,
        /** @var array<int, string> */
        public array $targetedHosts = [],
        /** @var array<int, string> */
        public array $attemptedUsers = [],
    ) {}

    /** @return array<int, self> */
    public static function fromSqliteOutput(string $output): array
    {
        if (empty(trim($output))) {
            return [];
        }

        $lines = explode("\n", trim($output));
        $bans = [];
        $now = now();

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            $parts = explode('|', $line);

            if (count($parts) !== 5) {
                continue;
            }

            $bannedAt = Carbon::createFromTimestamp((int) $parts[2]);
            $banDuration = (int) $parts[3];
            $isPermanent = $banDuration < 0;

            $expiresAt = $isPermanent
                ? null
                : $bannedAt->copy()->addSeconds($banDuration);

            $isActive = $isPermanent || ($expiresAt && $expiresAt->isAfter($now));

            if (! $isActive) {
                continue;
            }

            $bans[] = new self(
                ip: $parts[1],
                jail: $parts[0],
                bannedAt: $bannedAt->toIso8601String(),
                banDuration: $banDuration,
                banCount: (int) $parts[4],
                expiresAt: $expiresAt?->toIso8601String(),
                bannedSinceHuman: $bannedAt->diffForHumans(),
                remainingTimeHuman: $isPermanent
                    ? 'Permanent'
                    : $expiresAt->diffForHumans(),
                isPermanent: $isPermanent,
            );
        }

        return $bans;
    }
}
