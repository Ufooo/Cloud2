<?php

namespace Nip\SshKey\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\SshKey\Concerns\GeneratesSshKeyFingerprint;
use Nip\SshKey\Database\Factories\UserSshKeyFactory;

class UserSshKey extends Model
{
    /** @use HasFactory<UserSshKeyFactory> */
    use GeneratesSshKeyFingerprint, HasFactory;

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
}
