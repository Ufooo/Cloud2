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

    public function getBaseDomain(): ?string
    {
        $wildcardDomain = collect($this->domains)->first(fn ($d) => str_starts_with($d, '*.'));

        if ($wildcardDomain) {
            return substr($wildcardDomain, 2);
        }

        return $this->domains[0] ?? null;
    }
}
