<?php

namespace Nip\Server\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
        'run_as_user',
        'content',
        'output',
        'exit_code',
        'status',
        'dismissed_at',
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
            'dismissed_at' => 'datetime',
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

    /**
     * @return MorphTo<Model, $this>
     */
    public function resource(): MorphTo
    {
        return $this->morphTo('resource', 'resource_type', 'resource_id');
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

    protected function displayableName(): Attribute
    {
        return Attribute::get(function (): string {
            $resourceLabels = [
                'certificate' => 'SSL Certificate',
                'site' => 'Site',
                'database' => 'Database',
                'database_user' => 'Database User',
                'background_process' => 'Background Process',
                'scheduled_job' => 'Scheduled Job',
                'unix_user' => 'Unix User',
                'php_version' => 'PHP Version',
                'firewall_rule' => 'Firewall Rule',
                'ssh_key' => 'SSH Key',
                'domain' => 'Domain',
            ];

            return $resourceLabels[$this->resource_type] ?? ucfirst(str_replace('_', ' ', $this->resource_type));
        });
    }

    protected function errorMessage(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->isFailed() || ! $this->output) {
                return null;
            }

            // Try to extract the last error message from output
            if (preg_match('/\[(?:FATAL )?ERROR\]\s*(.+?)$/m', $this->output, $matches)) {
                return trim($matches[1]);
            }

            // Get last non-empty line as error
            $lines = array_filter(explode("\n", trim($this->output)));
            $lastLine = end($lines);

            return $lastLine ? trim($lastLine) : 'Script execution failed';
        });
    }
}
