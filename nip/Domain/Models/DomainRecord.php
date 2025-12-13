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
}
