<?php

namespace Nip\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nip\Domain\Database\Factories\CertificateFactory;
use Nip\Domain\Enums\CertificateStatus;
use Nip\Domain\Enums\CertificateType;
use Nip\Site\Models\Site;

class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    protected static function newFactory(): CertificateFactory
    {
        return CertificateFactory::new();
    }

    protected $fillable = [
        'site_id',
        'type',
        'status',
        'domains',
        'active',
        'certificate',
        'private_key',
        'path',
        'verification_method',
        'key_algorithm',
        'isrg_root_chain',
        'verification_records',
        'acme_subdomains',
        'csr_country',
        'csr_state',
        'csr_city',
        'csr_organization',
        'csr_department',
        'source_certificate_id',
        'issued_at',
        'expires_at',
    ];

    protected $hidden = [
        'certificate',
        'private_key',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'status' => CertificateStatus::class,
            'domains' => 'array',
            'active' => 'boolean',
            'isrg_root_chain' => 'boolean',
            'verification_records' => 'array',
            'acme_subdomains' => 'array',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return HasMany<DomainRecord, $this>
     */
    public function domainRecords(): HasMany
    {
        return $this->hasMany(DomainRecord::class);
    }

    /**
     * @return BelongsTo<Certificate, $this>
     */
    public function sourceCertificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'source_certificate_id');
    }

    public function isExpiringSoon(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        // Only check certificates that expire in the future within 30 days
        return $this->expires_at->isFuture() && now()->diffInDays($this->expires_at) <= 30;
    }

    public function getCertPath(): string
    {
        return $this->path ?? "/etc/nginx/ssl/{$this->getBaseDomain()}";
    }

    public function getSiteConfDir(): string
    {
        return "/etc/nginx/netipar-conf/{$this->site->domain}";
    }

    public function generateAcmeSubdomain(): string
    {
        return 'verify-'.substr(md5(uniqid((string) mt_rand(), true)), 0, 12);
    }

    /**
     * Get the ACME DNS target for a specific domain.
     */
    public function getAcmeDnsDomain(string $domain): ?string
    {
        $subdomain = $this->acme_subdomains[$domain] ?? null;

        if (! $subdomain) {
            return null;
        }

        return "{$subdomain}.".config('services.cloudflare.acme_dns_domain');
    }

    /**
     * Get all ACME subdomains as domain => full target mapping.
     *
     * @return array<string, string>
     */
    public function getAcmeDnsTargets(): array
    {
        $acmeDnsDomain = config('services.cloudflare.acme_dns_domain');

        return collect($this->acme_subdomains ?? [])
            ->mapWithKeys(fn ($subdomain, $domain) => [$domain => "{$subdomain}.{$acmeDnsDomain}"])
            ->all();
    }

    /**
     * Get the first ACME subdomain (for single-domain certificates).
     */
    public function getFirstAcmeSubdomain(): ?string
    {
        return collect($this->acme_subdomains ?? [])->first();
    }

    public function isWildcard(): bool
    {
        return collect($this->domains)->contains(fn ($d) => str_starts_with($d, '*.'));
    }

    /**
     * Check if this certificate covers the given domain.
     * Handles wildcard matching: *.example.com covers sub.example.com
     */
    public function coversDomain(string $domain): bool
    {
        $domain = strtolower($domain);

        foreach ($this->domains as $certDomain) {
            $certDomain = strtolower($certDomain);

            // Exact match
            if ($certDomain === $domain) {
                return true;
            }

            // Wildcard match: *.example.com covers sub.example.com
            if (str_starts_with($certDomain, '*.')) {
                $baseDomain = substr($certDomain, 2); // Remove *.
                // Check if domain ends with .baseDomain (e.g., sub.example.com ends with .example.com)
                if (str_ends_with($domain, '.'.$baseDomain)) {
                    return true;
                }
                // Also match the base domain itself (*.example.com often covers example.com too)
                if ($domain === $baseDomain) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if this certificate covers any of the given domains.
     *
     * @param  array<string>  $domains
     */
    public function coversAnyDomain(array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($this->coversDomain($domain)) {
                return true;
            }
        }

        return false;
    }

    public function getBaseDomain(): ?string
    {
        $wildcardDomain = collect($this->domains)->first(fn ($d) => str_starts_with($d, '*.'));

        if ($wildcardDomain) {
            return substr($wildcardDomain, 2);
        }

        return $this->domains[0] ?? null;
    }

    /**
     * Parse a PEM certificate and extract domains from Subject Alternative Names.
     *
     * @return array{domains: array<int, string>, issued_at: \Carbon\Carbon|null, expires_at: \Carbon\Carbon|null}|null
     */
    public static function parseCertificate(string $pemCertificate): ?array
    {
        $parsed = openssl_x509_parse($pemCertificate);

        if ($parsed === false) {
            return null;
        }

        $domains = [];

        // Extract CN (Common Name) as fallback
        if (isset($parsed['subject']['CN'])) {
            $domains[] = $parsed['subject']['CN'];
        }

        // Extract SAN (Subject Alternative Names)
        if (isset($parsed['extensions']['subjectAltName'])) {
            $san = $parsed['extensions']['subjectAltName'];
            // Format: "DNS:example.com, DNS:www.example.com"
            $entries = array_map('trim', explode(',', $san));
            foreach ($entries as $entry) {
                if (str_starts_with($entry, 'DNS:')) {
                    $domains[] = substr($entry, 4);
                }
            }
        }

        return [
            'domains' => array_values(array_unique($domains)),
            'issued_at' => isset($parsed['validFrom_time_t'])
                ? \Carbon\Carbon::createFromTimestamp($parsed['validFrom_time_t'])
                : null,
            'expires_at' => isset($parsed['validTo_time_t'])
                ? \Carbon\Carbon::createFromTimestamp($parsed['validTo_time_t'])
                : null,
        ];
    }
}
