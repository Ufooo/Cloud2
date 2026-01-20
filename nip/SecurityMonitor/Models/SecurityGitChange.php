<?php

namespace Nip\SecurityMonitor\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\SecurityMonitor\Database\Factories\SecurityGitChangeFactory;
use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\Site\Models\Site;

class SecurityGitChange extends Model
{
    /** @use HasFactory<SecurityGitChangeFactory> */
    use HasFactory;

    protected static function newFactory(): SecurityGitChangeFactory
    {
        return SecurityGitChangeFactory::new();
    }

    protected $fillable = [
        'scan_id',
        'site_id',
        'file_path',
        'change_type',
        'git_status_code',
        'is_whitelisted',
        'whitelisted_by',
        'whitelisted_at',
        'whitelist_reason',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'change_type' => GitChangeType::class,
            'is_whitelisted' => 'boolean',
            'whitelisted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SecurityScan, $this>
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(SecurityScan::class, 'scan_id');
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function whitelistedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'whitelisted_by');
    }
}
