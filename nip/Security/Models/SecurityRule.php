<?php

namespace Nip\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nip\Security\Database\Factories\SecurityRuleFactory;
use Nip\Security\Enums\SecurityRuleStatus;
use Nip\Site\Models\Site;

class SecurityRule extends Model
{
    /** @use HasFactory<SecurityRuleFactory> */
    use HasFactory;

    /**
     * The one shape a protected path may have, used by the form requests and by the provisioning
     * script alike. nginx matches a location against the normalized request URI, so a path written
     * any other way (an empty segment as in "//admin", a "." or ".." segment, a dotfile such as
     * "/.env") would give a location that never matches, or one that answers before the site's own
     * dotfile deny: the rule would read "installed" while protecting nothing. Every segment is
     * therefore non-empty and starts with something other than a dot.
     */
    public const PATH_PATTERN = '/^(?:\/[A-Za-z0-9_~-][A-Za-z0-9._~-]*)+$/D';

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

    protected static function newFactory(): SecurityRuleFactory
    {
        return SecurityRuleFactory::new();
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
