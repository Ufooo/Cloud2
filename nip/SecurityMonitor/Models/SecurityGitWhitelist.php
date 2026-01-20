<?php

namespace Nip\SecurityMonitor\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\SecurityMonitor\Database\Factories\SecurityGitWhitelistFactory;
use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\Site\Models\Site;

class SecurityGitWhitelist extends Model
{
    /** @use HasFactory<SecurityGitWhitelistFactory> */
    use HasFactory;

    protected static function newFactory(): SecurityGitWhitelistFactory
    {
        return SecurityGitWhitelistFactory::new();
    }

    protected $fillable = [
        'site_id',
        'file_path',
        'change_type',
        'reason',
        'created_by',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'change_type' => GitChangeType::class,
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
