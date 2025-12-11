<?php

namespace Nip\Php\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Php\Database\Factories\PhpVersionFactory;
use Nip\Php\Enums\PhpVersionStatus;
use Nip\Server\Models\Server;

class PhpVersion extends Model
{
    /** @use HasFactory<PhpVersionFactory> */
    use HasFactory;

    protected static function newFactory(): PhpVersionFactory
    {
        return PhpVersionFactory::new();
    }

    protected $fillable = [
        'server_id',
        'version',
        'is_cli_default',
        'is_site_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_cli_default' => 'boolean',
            'is_site_default' => 'boolean',
            'status' => PhpVersionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
