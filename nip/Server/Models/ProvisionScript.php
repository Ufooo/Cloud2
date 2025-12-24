<?php

namespace Nip\Server\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nip\Server\Database\Factories\ProvisionScriptFactory;
use Nip\Server\Enums\ProvisionScriptStatus;

class ProvisionScript extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'filename',
        'resource_type',
        'resource_id',
        'content',
        'output',
        'exit_code',
        'status',
        'executed_at',
    ];

    protected static function newFactory(): ProvisionScriptFactory
    {
        return ProvisionScriptFactory::new();
    }

    protected function casts(): array
    {
        return [
            'exit_code' => 'integer',
            'status' => ProvisionScriptStatus::class,
            'executed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === ProvisionScriptStatus::Completed && $this->exit_code === 0;
    }

    public function isFailed(): bool
    {
        return $this->status === ProvisionScriptStatus::Failed || ($this->exit_code !== null && $this->exit_code !== 0);
    }

    public function isExecuting(): bool
    {
        return $this->status === ProvisionScriptStatus::Executing;
    }

    public function isPending(): bool
    {
        return $this->status === ProvisionScriptStatus::Pending;
    }

    protected function duration(): Attribute
    {
        return Attribute::get(function (): ?float {
            if (! $this->executed_at || ! $this->created_at) {
                return null;
            }

            return $this->executed_at->diffInSeconds($this->created_at);
        });
    }
}
