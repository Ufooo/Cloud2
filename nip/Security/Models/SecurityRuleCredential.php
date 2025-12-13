<?php

namespace Nip\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityRuleCredential extends Model
{
    protected $fillable = [
        'security_rule_id',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * @return BelongsTo<SecurityRule, $this>
     */
    public function securityRule(): BelongsTo
    {
        return $this->belongsTo(SecurityRule::class);
    }
}
