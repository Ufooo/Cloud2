<?php

namespace Nip\Database\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Nip\Database\Database\Factories\DatabaseUserFactory;
use Nip\Database\Enums\DatabaseUserStatus;
use Nip\Server\Models\Server;

class DatabaseUser extends Model
{
    /** @use HasFactory<DatabaseUserFactory> */
    use HasFactory;

    protected $table = 'database_users';

    protected static function newFactory(): DatabaseUserFactory
    {
        return DatabaseUserFactory::new();
    }

    protected $fillable = [
        'server_id',
        'username',
        'password',
        'readonly',
        'status',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'readonly' => 'boolean',
            'status' => DatabaseUserStatus::class,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function hidden(): array
    {
        return ['password'];
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsToMany<Database, $this>
     */
    public function databases(): BelongsToMany
    {
        return $this->belongsToMany(Database::class, 'database_database_user');
    }
}
