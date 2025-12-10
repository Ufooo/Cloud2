<?php

namespace Nip\SshKey\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Server\Models\Server;
use Nip\SshKey\Database\Factories\SshKeyFactory;
use Nip\UnixUser\Models\UnixUser;

class SshKey extends Model
{
    /** @use HasFactory<SshKeyFactory> */
    use HasFactory;

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

    protected static function booted(): void
    {
        static::creating(function (SshKey $key) {
            if (empty($key->fingerprint) && ! empty($key->public_key)) {
                $key->fingerprint = self::generateFingerprint($key->public_key);
            }
        });
    }

    public static function generateFingerprint(string $publicKey): string
    {
        $keyData = preg_replace('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|ecdsa-sha2-nistp521)\s+/', '', trim($publicKey));
        $keyData = preg_replace('/\s+.*$/', '', $keyData);

        $decoded = base64_decode($keyData, true);

        if ($decoded === false) {
            return 'Invalid key';
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $decoded, true)), '=');
    }
}
