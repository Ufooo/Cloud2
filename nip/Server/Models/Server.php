<?php

namespace Nip\Server\Models;

use App\Models\Concerns\HasEnumDisplayAttributes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Database\Models\Database;
use Nip\Database\Models\DatabaseUser;
use Nip\Network\Models\FirewallRule;
use Nip\Php\Enums\PhpVersion;
use Nip\Php\Models\PhpSetting;
use Nip\Php\Models\PhpVersion as PhpVersionModel;
use Nip\Scheduler\Models\ScheduledJob;
use Nip\Server\Data\ProvisioningStepData;
use Nip\Server\Database\Factories\ServerFactory;
use Nip\Server\Enums\DatabaseType;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\ProvisioningStep;
use Nip\Server\Enums\ServerProvider;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Enums\ServerType;
use Nip\Server\Enums\Timezone;
use Nip\Site\Models\Site;
use Nip\SshKey\Models\SshKey;
use Nip\UnixUser\Models\UnixUser;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Server extends Model
{
    use HasEnumDisplayAttributes, HasFactory, HasSlug, SoftDeletes;

    public const DEFAULT_PHP_VERSION = '8.4';

    protected static function booted(): void
    {
        static::deleting(function (Server $server) {
            // Delete all related records when server is soft deleted
            $server->databases()->forceDelete();
            $server->databaseUsers()->forceDelete();
            $server->sites()->forceDelete();
            $server->unixUsers()->forceDelete();
            $server->sshKeys()->forceDelete();
            $server->phpVersions()->forceDelete();
            $server->phpSetting()->forceDelete();
            $server->firewallRules()->forceDelete();
            $server->backgroundProcesses()->forceDelete();
            $server->scheduledJobs()->forceDelete();
        });
    }

    protected static function newFactory(): ServerFactory
    {
        return ServerFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'provider',
        'provider_server_id',
        'type',
        'status',
        'provisioning_token',
        'provision_step',
        'ip_address',
        'private_ip_address',
        'ssh_port',
        'ssh_public_key',
        'ssh_private_key',
        'php_version',
        'database_type',
        'database_password',
        'git_public_key',
        'db_status',
        'ubuntu_version',
        'timezone',
        'notes',
        'avatar_color',
        'services',
        'cloud_provider_url',
        'is_ready',
        'last_connected_at',
        'uptime_seconds',
        'load_avg_1',
        'load_avg_5',
        'load_avg_15',
        'cpu_percent',
        'ram_total_bytes',
        'ram_used_bytes',
        'ram_percent',
        'disk_total_bytes',
        'disk_used_bytes',
        'disk_percent',
        'last_metrics_at',
    ];

    protected $hidden = [
        'ssh_private_key',
        'database_password',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ServerProvider::class,
            'type' => ServerType::class,
            'status' => ServerStatus::class,
            'database_type' => DatabaseType::class,
            'timezone' => Timezone::class,
            'avatar_color' => IdentityColor::class,
            'services' => 'array',
            'is_ready' => 'boolean',
            'provision_step' => 'integer',
            'last_connected_at' => 'datetime',
            'uptime_seconds' => 'integer',
            'load_avg_1' => 'decimal:2',
            'load_avg_5' => 'decimal:2',
            'load_avg_15' => 'decimal:2',
            'cpu_percent' => 'integer',
            'ram_total_bytes' => 'integer',
            'ram_used_bytes' => 'integer',
            'ram_percent' => 'integer',
            'disk_total_bytes' => 'integer',
            'disk_used_bytes' => 'integer',
            'disk_percent' => 'integer',
            'last_metrics_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<FirewallRule, $this>
     */
    public function firewallRules(): HasMany
    {
        return $this->hasMany(FirewallRule::class);
    }

    /**
     * @return HasMany<UnixUser, $this>
     */
    public function unixUsers(): HasMany
    {
        return $this->hasMany(UnixUser::class);
    }

    /**
     * @return HasMany<SshKey, $this>
     */
    public function sshKeys(): HasMany
    {
        return $this->hasMany(SshKey::class);
    }

    /**
     * @return HasOne<PhpSetting, $this>
     */
    public function phpSetting(): HasOne
    {
        return $this->hasOne(PhpSetting::class);
    }

    /**
     * @return HasMany<PhpVersionModel, $this>
     */
    public function phpVersions(): HasMany
    {
        return $this->hasMany(PhpVersionModel::class);
    }

    /**
     * @return HasMany<BackgroundProcess, $this>
     */
    public function backgroundProcesses(): HasMany
    {
        return $this->hasMany(BackgroundProcess::class);
    }

    /**
     * @return HasMany<ScheduledJob, $this>
     */
    public function scheduledJobs(): HasMany
    {
        return $this->hasMany(ScheduledJob::class);
    }

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return HasMany<Database, $this>
     */
    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }

    /**
     * @return HasMany<DatabaseUser, $this>
     */
    public function databaseUsers(): HasMany
    {
        return $this->hasMany(DatabaseUser::class);
    }

    protected function displayableType(): Attribute
    {
        return Attribute::get(fn () => $this->getEnumLabel('type'));
    }

    protected function phpVersionString(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->php_version) {
                return self::DEFAULT_PHP_VERSION;
            }

            return PhpVersion::tryFrom($this->php_version)?->version() ?? self::DEFAULT_PHP_VERSION;
        });
    }

    protected function displayableProvider(): Attribute
    {
        return Attribute::get(fn () => $this->getEnumLabel('provider'));
    }

    protected function displayableDatabaseType(): Attribute
    {
        return Attribute::get(fn () => $this->getEnumLabel('database_type'));
    }

    protected function provisioningCommand(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->provisioning_token) {
                return null;
            }

            $url = route('provisioning.script', ['server' => $this->id, 'token' => $this->provisioning_token]);

            return "wget -O- '{$url}' | bash";
        });
    }

    /**
     * @return Attribute<array<ProvisioningStepData>, never>
     */
    protected function provisioningSteps(): Attribute
    {
        return Attribute::get(fn () => $this->getProvisioningSteps());
    }

    /**
     * @return array<ProvisioningStepData>
     */
    public function getProvisioningSteps(): array
    {
        $steps = [
            ProvisioningStep::WaitingForServer,
            ProvisioningStep::PreparingServer,
            ProvisioningStep::ConfiguringSwap,
            ProvisioningStep::InstallingBaseDependencies,
        ];

        if ($this->php_version) {
            $steps[] = ProvisioningStep::InstallingPhp;
        }

        $steps[] = ProvisioningStep::InstallingNginx;

        if ($this->database_type) {
            $steps[] = ProvisioningStep::InstallingDatabase;
        }

        $steps[] = ProvisioningStep::InstallingRedis;
        $steps[] = ProvisioningStep::MakingFinalTouches;

        return array_map(
            fn (ProvisioningStep $step) => new ProvisioningStepData(
                value: $step->value,
                label: $this->getStepLabel($step),
                description: $step->description(),
            ),
            $steps
        );
    }

    private function getStepLabel(ProvisioningStep $step): string
    {
        if ($step === ProvisioningStep::InstallingDatabase && $this->displayable_database_type) {
            return "Installing {$this->displayable_database_type}";
        }

        return $step->label();
    }

    public function getSshPrivateKey(): ?string
    {
        return $this->ssh_private_key;
    }

    protected function uptimeFormatted(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->uptime_seconds) {
                return null;
            }

            $days = intdiv($this->uptime_seconds, 86400);
            $hours = intdiv($this->uptime_seconds % 86400, 3600);

            return "{$days}d {$hours}h";
        });
    }

    protected function loadAvgFormatted(): Attribute
    {
        return Attribute::get(function () {
            if ($this->load_avg_1 === null) {
                return null;
            }

            return "{$this->load_avg_1}, {$this->load_avg_5}, {$this->load_avg_15}";
        });
    }
}
