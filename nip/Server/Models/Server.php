<?php

namespace Nip\Server\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nip\Network\Models\FirewallRule;
use Nip\Php\Enums\PhpVersion;
use Nip\Server\Data\ProvisioningStepData;
use Nip\Server\Database\Factories\ServerFactory;
use Nip\Server\Enums\DatabaseType;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\ProvisioningStep;
use Nip\Server\Enums\ServerProvider;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Enums\ServerType;
use Nip\Server\Enums\Timezone;
use Nip\UnixUser\Models\UnixUser;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Server extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

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
        'displayable_provider',
        'displayable_database_type',
        'cloud_provider_url',
        'is_ready',
        'last_connected_at',
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
     * @return HasMany<\Nip\SshKey\Models\SshKey, $this>
     */
    public function sshKeys(): HasMany
    {
        return $this->hasMany(\Nip\SshKey\Models\SshKey::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\Nip\Php\Models\PhpSetting, $this>
     */
    public function phpSetting(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\Nip\Php\Models\PhpSetting::class);
    }

    /**
     * @return HasMany<\Nip\Php\Models\PhpVersion, $this>
     */
    public function phpVersions(): HasMany
    {
        return $this->hasMany(\Nip\Php\Models\PhpVersion::class);
    }

    /**
     * @return HasMany<\Nip\BackgroundProcess\Models\BackgroundProcess, $this>
     */
    public function backgroundProcesses(): HasMany
    {
        return $this->hasMany(\Nip\BackgroundProcess\Models\BackgroundProcess::class);
    }

    /**
     * @return HasMany<\Nip\Scheduler\Models\ScheduledJob, $this>
     */
    public function scheduledJobs(): HasMany
    {
        return $this->hasMany(\Nip\Scheduler\Models\ScheduledJob::class);
    }

    /**
     * @return HasMany<\Nip\Site\Models\Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(\Nip\Site\Models\Site::class);
    }

    /**
     * @return HasMany<\Nip\Database\Models\Database, $this>
     */
    public function databases(): HasMany
    {
        return $this->hasMany(\Nip\Database\Models\Database::class);
    }

    /**
     * @return HasMany<\Nip\Database\Models\DatabaseUser, $this>
     */
    public function databaseUsers(): HasMany
    {
        return $this->hasMany(\Nip\Database\Models\DatabaseUser::class);
    }

    protected function displayableType(): Attribute
    {
        return Attribute::get(fn () => $this->type?->label());
    }

    protected function displayablePhpVersion(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->php_version) {
                return null;
            }

            $phpVersion = PhpVersion::tryFrom($this->php_version);

            return $phpVersion?->label();
        });
    }

    protected function phpVersionString(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->php_version) {
                return '8.4';
            }

            return PhpVersion::tryFrom($this->php_version)?->version() ?? '8.4';
        });
    }

    protected function displayableProvider(): Attribute
    {
        return Attribute::get(fn () => $this->provider?->label());
    }

    protected function displayableDatabaseType(): Attribute
    {
        return Attribute::get(fn () => $this->database_type?->label());
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
}
