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

    public function isExpiringSoon(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->diffInDays() <= 30;
    }
}
