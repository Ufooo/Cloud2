<?php

namespace Nip\Deployment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Deployment\Database\Factories\DeploymentFactory;
use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Site\Models\Site;
use Nip\User\Models\User;

class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory;

    protected static function newFactory(): DeploymentFactory
    {
        return DeploymentFactory::new();
    }

    protected $fillable = [
        'site_id',
        'user_id',
        'status',
        'commit_hash',
        'commit_message',
        'commit_author',
        'branch',
        'output',
        'started_at',
        'ended_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => DeploymentStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getShortCommitHash(): ?string
    {
        if (! $this->commit_hash) {
            return null;
        }

        return substr($this->commit_hash, 0, 7);
    }

    public function getDuration(): ?int
    {
        if (! $this->started_at || ! $this->ended_at) {
            return null;
        }

        return $this->ended_at->diffInSeconds($this->started_at);
    }
}
