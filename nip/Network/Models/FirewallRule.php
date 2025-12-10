<?php

namespace Nip\Network\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Network\Enums\RuleStatus;
use Nip\Network\Enums\RuleType;
use Nip\Server\Models\Server;

class FirewallRule extends Model
{
    protected $fillable = [
        'server_id',
        'name',
        'port',
        'ip_address',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => RuleType::class,
            'status' => RuleStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
