<?php

namespace Nip\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Security\Database\Factories\SecurityRuleCredentialFactory;

class SecurityRuleCredential extends Model
{
    /** @use HasFactory<SecurityRuleCredentialFactory> */
    use HasFactory;

    protected $fillable = [
        'security_rule_id',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected static function newFactory(): SecurityRuleCredentialFactory
    {
        return SecurityRuleCredentialFactory::new();
    }

    /**
     * @return BelongsTo<SecurityRule, $this>
     */
    public function securityRule(): BelongsTo
    {
        return $this->belongsTo(SecurityRule::class);
    }
}
