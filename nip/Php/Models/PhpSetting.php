<?php

namespace Nip\Php\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Php\Database\Factories\PhpSettingFactory;
use Nip\Server\Models\Server;

class PhpSetting extends Model
{
    /** @use HasFactory<PhpSettingFactory> */
    use HasFactory;

    protected static function newFactory(): PhpSettingFactory
    {
        return PhpSettingFactory::new();
    }

    protected $fillable = [
        'server_id',
        'max_upload_size',
        'max_execution_time',
        'opcache_enabled',
    ];

    protected function casts(): array
    {
        return [
            'opcache_enabled' => 'boolean',
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
