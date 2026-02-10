<?php

namespace Nip\Network\Services;

use Illuminate\Support\Facades\Cache;
use Nip\Network\Data\BannedIpData;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\SSHService;
use Stevebauman\Location\Facades\Location;

class Fail2banService
{
    public function __construct(
        private SSHService $ssh
    ) {}

    /** @return array<int, BannedIpData> */
    public function getBannedIps(Server $server): array
    {
        $this->ssh->connect($server);

        $query = 'SELECT jail, ip, timeofban, bantime, bancount FROM bans ORDER BY timeofban DESC';

        $result = $this->ssh->exec(
            "sqlite3 /var/lib/fail2ban/fail2ban.sqlite3 \"{$query}\" 2>/dev/null"
        );

        if ($result->failed()) {
            return [];
        }

        $bans = BannedIpData::fromSqliteOutput($result->getTrimmedOutput());

        $this->enrichWithAttackRequests($server, $bans);
        $this->enrichWithAttemptedUsers($server, $bans);

        return $bans;
    }

    /** @param  array<int, BannedIpData>  $bans */
    private function enrichWithAttackRequests(Server $server, array $bans): void
    {
        $nginxBans = array_filter($bans, fn (BannedIpData $ban) => str_starts_with($ban->jail, 'nginx-'));

        if (empty($nginxBans)) {
            return;
        }

        $ips = array_unique(array_map(fn (BannedIpData $ban) => $ban->ip, $nginxBans));
        $grepPattern = implode('|', array_map(fn (string $ip) => preg_quote($ip, '/'), $ips));

        $result = $this->ssh->exec(
            "grep -E '^({$grepPattern}) ' /var/log/nginx/access.log 2>/dev/null | awk '{print \$1, \$NF, \$7}' | sort -u | head -200"
        );

        if ($result->failed()) {
            return;
        }

        $requestMap = [];

        foreach (explode("\n", trim($result->getTrimmedOutput())) as $line) {
            $parts = explode(' ', trim($line), 3);

            if (count($parts) !== 3 || empty($parts[2])) {
                continue;
            }

            [$ip, $host, $path] = $parts;
            $requestMap[$ip][] = "{$host}{$path}";
        }

        foreach ($bans as $ban) {
            if (isset($requestMap[$ban->ip])) {
                $ban->attackRequests = array_values(array_unique($requestMap[$ban->ip]));
            }
        }
    }

    /** @param  array<int, BannedIpData>  $bans */
    private function enrichWithAttemptedUsers(Server $server, array $bans): void
    {
        $sshBans = array_filter($bans, fn (BannedIpData $ban) => $ban->jail === 'sshd');

        if (empty($sshBans)) {
            return;
        }

        $ips = array_unique(array_map(fn (BannedIpData $ban) => $ban->ip, $sshBans));
        $grepPattern = implode('|', array_map(fn (string $ip) => preg_quote($ip, '/'), $ips));

        $result = $this->ssh->exec(
            "grep -E 'Invalid user .* from ({$grepPattern}) ' /var/log/auth.log 2>/dev/null | sed 's/.*Invalid user \\(.*\\) from \\([^ ]*\\).*/\\2 \\1/' | sort -u"
        );

        if ($result->failed()) {
            return;
        }

        $userMap = [];

        foreach (explode("\n", trim($result->getTrimmedOutput())) as $line) {
            $parts = explode(' ', trim($line), 2);

            if (count($parts) !== 2 || empty($parts[1])) {
                continue;
            }

            $userMap[$parts[0]][] = $parts[1];
        }

        foreach ($bans as $ban) {
            if (isset($userMap[$ban->ip])) {
                $ban->attemptedUsers = array_values(array_unique($userMap[$ban->ip]));
            }
        }
    }

    /**
     * @param  array<int, string>  $ips
     * @return array<string, array{country: ?string, countryCode: ?string}>
     */
    public function resolveGeoIp(array $ips): array
    {
        $result = [];

        foreach ($ips as $ip) {
            $cacheKey = "geoip:{$ip}";

            $result[$ip] = Cache::remember($cacheKey, now()->addDay(), function () use ($ip) {
                try {
                    $position = Location::get($ip);

                    if ($position === false || $position === null) {
                        return ['country' => null, 'countryCode' => null];
                    }

                    return [
                        'country' => $position->countryName,
                        'countryCode' => $position->countryCode,
                    ];
                } catch (\Exception) {
                    return ['country' => null, 'countryCode' => null];
                }
            });
        }

        return $result;
    }

    public function unbanIp(Server $server, string $jail, string $ip): bool
    {
        $this->ssh->connect($server);

        $result = $this->ssh->exec(
            "fail2ban-client set {$jail} unbanip {$ip} 2>/dev/null"
        );

        return $result->isSuccessful();
    }
}
