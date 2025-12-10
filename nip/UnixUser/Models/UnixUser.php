<?php

namespace Nip\UnixUser\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Server\Models\Server;
use Nip\UnixUser\Enums\UserStatus;

class UnixUser extends Model
{
    protected $fillable = [
        'server_id',
        'username',
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
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
        ];
    }
}
