<?php

namespace Nip\Scheduler\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Scheduler\Database\Factories\ScheduledJobFactory;
use Nip\Scheduler\Enums\CronFrequency;
use Nip\Scheduler\Enums\GracePeriod;
use Nip\Scheduler\Enums\JobStatus;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

class ScheduledJob extends Model
{
    /** @use HasFactory<ScheduledJobFactory> */
    use HasFactory;

    protected static function newFactory(): ScheduledJobFactory
    {
        return ScheduledJobFactory::new();
    }

    protected $fillable = [
        'server_id',
        'site_id',
        'name',
        'command',
        'user',
        'frequency',
        'cron',
        'heartbeat_enabled',
        'heartbeat_url',
        'grace_period',
        'status',
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'frequency' => CronFrequency::class,
            'grace_period' => GracePeriod::class,
            'status' => JobStatus::class,
            'heartbeat_enabled' => 'boolean',
        ];
    }

    public function getEffectiveCron(): string
    {
        if ($this->frequency === CronFrequency::Custom) {
            return $this->cron ?? '* * * * *';
        }

        return $this->frequency->cronExpression() ?? '* * * * *';
    }
}
