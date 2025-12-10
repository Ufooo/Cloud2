<?php

namespace Nip\SshKey\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Server\Models\Server;
use Nip\SshKey\Concerns\GeneratesSshKeyFingerprint;
use Nip\SshKey\Database\Factories\SshKeyFactory;
use Nip\UnixUser\Models\UnixUser;

class SshKey extends Model
{
    /** @use HasFactory<SshKeyFactory> */
    use GeneratesSshKeyFingerprint, HasFactory;

    protected static function newFactory(): SshKeyFactory
    {
        return SshKeyFactory::new();
    }

    protected $fillable = [
        'server_id',
        'unix_user_id',
        'name',
        'public_key',
        'fingerprint',
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo<UnixUser, $this>
     */
    public function unixUser(): BelongsTo
    {
        return $this->belongsTo(UnixUser::class);
    }
}
