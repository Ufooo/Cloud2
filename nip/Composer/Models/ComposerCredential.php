<?php

namespace Nip\Composer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Composer\Database\Factories\ComposerCredentialFactory;
use Nip\Composer\Enums\ComposerCredentialStatus;
use Nip\Site\Models\Site;
use Nip\UnixUser\Models\UnixUser;

class ComposerCredential extends Model
{
    /** @use HasFactory<ComposerCredentialFactory> */
    use HasFactory;

    protected $fillable = [
        'unix_user_id',
        'site_id',
        'repository',
        'username',
        'password',
        'status',
    ];

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComposerCredentialStatus::class,
        ];
    }

    protected static function newFactory(): ComposerCredentialFactory
    {
        return ComposerCredentialFactory::new();
    }

    /**
     * @return BelongsTo<UnixUser, $this>
     */
    public function unixUser(): BelongsTo
    {
        return $this->belongsTo(UnixUser::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isSiteLevel(): bool
    {
        return $this->site_id !== null;
    }

    public function isUserLevel(): bool
    {
        return $this->site_id === null;
    }
}
