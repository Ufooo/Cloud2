<?php

namespace Nip\UnixUser\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nip\Server\Models\Server;
use Nip\UnixUser\Database\Factories\UnixUserFactory;
use Nip\UnixUser\Enums\UserStatus;

class UnixUser extends Model
{
    /** @use HasFactory<UnixUserFactory> */
    use HasFactory;

    protected static function newFactory(): UnixUserFactory
    {
        return UnixUserFactory::new();
    }

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
     * @return HasMany<\Nip\SshKey\Models\SshKey, $this>
     */
    public function sshKeys(): HasMany
    {
        return $this->hasMany(\Nip\SshKey\Models\SshKey::class);
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
