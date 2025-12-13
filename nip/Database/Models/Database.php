<?php

namespace Nip\Database\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Nip\Database\Database\Factories\DatabaseFactory;
use Nip\Server\Models\Server;
use Nip\Site\Models\Site;

class Database extends Model
{
    /** @use HasFactory<DatabaseFactory> */
    use HasFactory;

    protected $table = 'databases';

    protected static function newFactory(): DatabaseFactory
    {
        return DatabaseFactory::new();
    }

    protected $fillable = [
        'server_id',
        'site_id',
        'name',
        'size',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsToMany<DatabaseUser, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(DatabaseUser::class, 'database_database_user');
    }
}
