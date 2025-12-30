<?php

namespace Nip\Domain\Services;

class CloudflareService
{
    protected string $acmeDnsDomain;

    public function __construct()
    {
        $this->acmeDnsDomain = config('services.cloudflare.acme_dns_domain');
    }

    public function verifyCnameRecord(string $domain, string $expectedTarget): bool
    {
        $challengeDomain = "_acme-challenge.{$domain}";
        $expectedTarget = rtrim($expectedTarget, '.');

        // Try using dig with Cloudflare DNS for faster propagation detection
        $digResult = shell_exec("dig +short CNAME {$challengeDomain} @1.1.1.1 2>/dev/null");
        if ($digResult) {
            $target = rtrim(trim($digResult), '.');

            return strcasecmp($target, $expectedTarget) === 0;
        }

        // Fallback to PHP's dns_get_record (uses local resolver)
        $result = dns_get_record($challengeDomain, DNS_CNAME);

        if (empty($result)) {
            return false;
        }

        $target = rtrim($result[0]['target'] ?? '', '.');

        return strcasecmp($target, $expectedTarget) === 0;
    }

    public function getAcmeDnsDomain(): string
    {
        return $this->acmeDnsDomain;
    }
}
