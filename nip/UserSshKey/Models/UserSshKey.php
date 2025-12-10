<?php

namespace Nip\UserSshKey\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\UserSshKey\Database\Factories\UserSshKeyFactory;

class UserSshKey extends Model
{
    /** @use HasFactory<UserSshKeyFactory> */
    use HasFactory;

    protected static function newFactory(): UserSshKeyFactory
    {
        return UserSshKeyFactory::new();
    }

    protected $fillable = [
        'user_id',
        'name',
        'public_key',
        'fingerprint',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function (UserSshKey $key) {
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
