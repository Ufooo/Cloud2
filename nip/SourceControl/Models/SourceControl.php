<?php

namespace Nip\SourceControl\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\SourceControl\Database\Factories\SourceControlFactory;
use Nip\SourceControl\Enums\SourceControlProvider;

class SourceControl extends Model
{
    use HasFactory;

    protected $table = 'source_controls';

    protected $fillable = [
        'user_id',
        'provider',
        'name',
        'provider_user_id',
        'token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $hidden = [
        'token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'provider' => SourceControlProvider::class,
            'token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    protected static function newFactory(): SourceControlFactory
    {
        return SourceControlFactory::new();
    }
}
