<?php

namespace Nip\Redirect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Redirect\Enums\RedirectRuleStatus;
use Nip\Redirect\Enums\RedirectType;
use Nip\Site\Models\Site;

class RedirectRule extends Model
{
    protected $fillable = [
        'site_id',
        'from',
        'to',
        'type',
        'status',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => RedirectType::class,
            'status' => RedirectRuleStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
