<?php

namespace Nip\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nip\Security\Enums\SecurityRuleStatus;
use Nip\Site\Models\Site;

class SecurityRule extends Model
{
    protected $fillable = [
        'site_id',
        'name',
        'path',
        'status',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => SecurityRuleStatus::class,
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
     * @return HasMany<SecurityRuleCredential, $this>
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(SecurityRuleCredential::class);
    }
}
