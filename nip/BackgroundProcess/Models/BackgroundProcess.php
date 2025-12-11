<?php

namespace Nip\BackgroundProcess\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\BackgroundProcess\Database\Factories\BackgroundProcessFactory;
use Nip\BackgroundProcess\Enums\ProcessStatus;
use Nip\BackgroundProcess\Enums\StopSignal;
use Nip\BackgroundProcess\Enums\SupervisorProcessStatus;
use Nip\Server\Models\Server;

class BackgroundProcess extends Model
{
    /** @use HasFactory<BackgroundProcessFactory> */
    use HasFactory;

    protected static function newFactory(): BackgroundProcessFactory
    {
        return BackgroundProcessFactory::new();
    }

    protected $fillable = [
        'server_id',
        'name',
        'command',
        'directory',
        'user',
        'processes',
        'startsecs',
        'stopwaitsecs',
        'stopsignal',
        'status',
        'supervisor_process_status',
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'processes' => 'integer',
            'startsecs' => 'integer',
            'stopwaitsecs' => 'integer',
            'stopsignal' => StopSignal::class,
            'status' => ProcessStatus::class,
            'supervisor_process_status' => SupervisorProcessStatus::class,
        ];
    }
}
