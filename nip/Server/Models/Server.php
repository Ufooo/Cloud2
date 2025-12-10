<?php

namespace Nip\Server\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nip\Server\Data\ProvisioningStepData;
use Nip\Server\Database\Factories\ServerFactory;
use Nip\Server\Enums\DatabaseType;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Enums\PhpVersion;
use Nip\Server\Enums\ProvisioningStep;
use Nip\Server\Enums\ServerProvider;
use Nip\Server\Enums\ServerStatus;
use Nip\Server\Enums\ServerType;
use Nip\Server\Enums\Timezone;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Server extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected $appends = [
        'provisioning_steps',
    ];

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
        'php_version',
        'database_type',
        'db_status',
        'ubuntu_version',
        'timezone',
        'notes',
        'avatar_color',
        'services',
        'region',
        'displayable_provider',
        'displayable_database_type',
        'cloud_provider_url',
        'is_ready',
        'last_connected_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ServerProvider::class,
            'type' => ServerType::class,
            'status' => ServerStatus::class,
            'timezone' => Timezone::class,
            'avatar_color' => IdentityColor::class,
            'services' => 'array',
            'region' => 'array',
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

    protected function displayableProvider(): Attribute
    {
        return Attribute::get(fn () => $this->provider?->label());
    }

    protected function displayableDatabaseType(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->database_type) {
                return null;
            }

            $databaseType = DatabaseType::tryFrom($this->database_type);

            return $databaseType?->label();
        });
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
}
