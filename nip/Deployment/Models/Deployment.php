<?php

namespace Nip\Deployment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Deployment\Database\Factories\DeploymentFactory;
use Nip\Deployment\Enums\DeploymentStatus;
use Nip\Site\Models\Site;

class Deployment extends Model
{
    private const SHORT_HASH_LENGTH = 7;

    private const SECONDS_PER_MINUTE = 60;

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
        'callback_token',
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

        return substr($this->commit_hash, 0, self::SHORT_HASH_LENGTH);
    }

    public function getCommitSubject(): ?string
    {
        if (! $this->commit_message) {
            return null;
        }

        return strtok($this->commit_message, "\n");
    }

    public function getDuration(): ?int
    {
        if (! $this->started_at || ! $this->ended_at) {
            return null;
        }

        return $this->ended_at->diffInSeconds($this->started_at);
    }

    public function getDurationForHumans(): ?string
    {
        $duration = $this->getDuration();

        if ($duration === null) {
            return null;
        }

        if ($duration < self::SECONDS_PER_MINUTE) {
            return "{$duration} seconds";
        }

        $minutes = (int) floor($duration / self::SECONDS_PER_MINUTE);
        $seconds = $duration % self::SECONDS_PER_MINUTE;

        if ($seconds === 0) {
            return "{$minutes} minutes";
        }

        return "{$minutes}m {$seconds}s";
    }
}
