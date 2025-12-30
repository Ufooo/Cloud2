<?php

namespace Nip\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Domain\Database\Factories\DomainRecordFactory;
use Nip\Domain\Enums\DomainRecordStatus;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Site\Enums\WwwRedirectType;
use Nip\Site\Models\Site;

class DomainRecord extends Model
{
    /** @use HasFactory<DomainRecordFactory> */
    use HasFactory;

    protected static function newFactory(): DomainRecordFactory
    {
        return DomainRecordFactory::new();
    }

    protected $fillable = [
        'site_id',
        'certificate_id',
        'name',
        'type',
        'status',
        'www_redirect_type',
        'allow_wildcard',
        'acme_subdomains',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => DomainRecordType::class,
            'status' => DomainRecordStatus::class,
            'www_redirect_type' => WwwRedirectType::class,
            'allow_wildcard' => 'boolean',
            'acme_subdomains' => 'array',
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
     * @return BelongsTo<Certificate, $this>
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function getUrl(): string
    {
        return 'https://'.$this->name;
    }

    public function isPrimary(): bool
    {
        return $this->type === DomainRecordType::Primary;
    }

    /**
     * Get or generate ACME subdomains for DNS-01 verification.
     * Returns a map of domain => subdomain based on www redirect settings.
     *
     * @return array<string, string>
     */
    public function getOrCreateAcmeSubdomains(): array
    {
        $requiredDomains = $this->getDomainsRequiringVerification();

        // Check if we already have all required subdomains
        $existingSubdomains = $this->acme_subdomains ?? [];
        $needsUpdate = false;

        foreach ($requiredDomains as $domain) {
            if (! isset($existingSubdomains[$domain])) {
                $existingSubdomains[$domain] = $this->generateAcmeSubdomain();
                $needsUpdate = true;
            }
        }

        // Save if we generated new subdomains
        if ($needsUpdate) {
            $this->acme_subdomains = $existingSubdomains;
            $this->save();
        }

        // Return only the required domains (in case www redirect changed)
        return array_intersect_key($existingSubdomains, array_flip($requiredDomains));
    }

    /**
     * Get domains that require DNS verification based on www redirect settings.
     *
     * @return array<int, string>
     */
    public function getDomainsRequiringVerification(): array
    {
        $domains = [$this->name];

        // Wildcard domains only need the base domain
        if ($this->allow_wildcard) {
            return $domains;
        }

        // Non-wildcard: check www redirect settings
        if ($this->www_redirect_type === WwwRedirectType::ToWww ||
            $this->www_redirect_type === WwwRedirectType::FromWww) {
            $domains[] = 'www.'.$this->name;
        }

        return $domains;
    }

    /**
     * Generate a unique ACME subdomain.
     */
    public function generateAcmeSubdomain(): string
    {
        return 'verify-'.substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
    }

    /**
     * Build verification records from subdomains array.
     *
     * @param  array<string, string>  $subdomains  Map of domain => subdomain
     * @param  array<string, bool>  $verifiedStatus  Map of domain => verified status
     * @return array<int, array{requiresVerification: bool, verified: bool, type: string, name: string, value: string, ttl: int}>
     */
    public function buildVerificationRecords(array $subdomains, array $verifiedStatus = []): array
    {
        if (empty($subdomains)) {
            return [];
        }

        $acmeDnsDomain = config('services.cloudflare.acme_dns_domain');

        return array_map(
            fn (string $domain, string $subdomain) => [
                'requiresVerification' => true,
                'verified' => $verifiedStatus[$domain] ?? false,
                'type' => 'CNAME',
                'name' => "_acme-challenge.{$domain}",
                'value' => "{$subdomain}.{$acmeDnsDomain}",
                'ttl' => 60,
            ],
            array_keys($subdomains),
            array_values($subdomains)
        );
    }

    /**
     * Get verification records from stored subdomains (no side effects).
     *
     * @return array<int, array{requiresVerification: bool, verified: bool, type: string, name: string, value: string, ttl: int}>
     */
    public function getVerificationRecords(): array
    {
        return $this->buildVerificationRecords($this->acme_subdomains ?? []);
    }
}
