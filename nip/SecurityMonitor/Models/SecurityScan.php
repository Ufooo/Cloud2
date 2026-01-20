<?php

namespace Nip\SecurityMonitor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nip\SecurityMonitor\Database\Factories\SecurityScanFactory;
use Nip\SecurityMonitor\Enums\ScanStatus;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

class SecurityScan extends Model
{
    /** @use HasFactory<SecurityScanFactory> */
    use HasFactory;

    protected static function newFactory(): SecurityScanFactory
    {
        return SecurityScanFactory::new();
    }

    protected $fillable = [
        'site_id',
        'server_id',
        'status',
        'started_at',
        'completed_at',
        'git_modified_count',
        'git_untracked_count',
        'git_deleted_count',
        'git_whitelisted_count',
        'git_new_count',
        'error_message',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ScanStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'git_modified_count' => 'integer',
            'git_untracked_count' => 'integer',
            'git_deleted_count' => 'integer',
            'git_whitelisted_count' => 'integer',
            'git_new_count' => 'integer',
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
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return HasMany<SecurityGitChange, $this>
     */
    public function gitChanges(): HasMany
    {
        return $this->hasMany(SecurityGitChange::class, 'scan_id');
    }
}
