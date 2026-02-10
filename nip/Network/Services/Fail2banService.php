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

        return BannedIpData::fromSqliteOutput($result->getTrimmedOutput());
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
