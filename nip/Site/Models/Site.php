<?php

namespace Nip\Site\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Nip\Composer\Models\ComposerCredential;
use Nip\Php\Enums\PhpVersion;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Models\Server;
use Nip\Site\Data\SiteProvisioningStepData;
use Nip\Site\Database\Factories\SiteFactory;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteProvisioningStep;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Enums\SiteType;
use Nip\Site\Enums\WwwRedirectType;
use Nip\SourceControl\Models\SourceControl;
use Nip\SourceControl\Services\GitHubService;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory, HasSlug;

    protected static function newFactory(): SiteFactory
    {
        return SiteFactory::new();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('domain')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'server_id',
        'source_control_id',
        'database_id',
        'database_user_id',
        'domain',
        'slug',
        'type',
        'status',
        'provisioning_step',
        'batch_id',
        'deploy_status',
        'www_redirect_type',
        'allow_wildcard',
        'user',
        'root_directory',
        'web_directory',
        'php_version',
        'package_manager',
        'build_command',
        'repository',
        'branch',
        'deploy_key',
        'deploy_script',
        'push_to_deploy',
        'auto_source',
        'deploy_hook_token',
        'deployment_retention',
        'zero_downtime',
        'healthcheck_endpoint',
        'environment',
        'avatar_color',
        'notes',
        'last_deployed_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => SiteType::class,
            'status' => SiteStatus::class,
            'provisioning_step' => SiteProvisioningStep::class,
            'deploy_status' => DeployStatus::class,
            'www_redirect_type' => WwwRedirectType::class,
            'allow_wildcard' => 'boolean',
            'package_manager' => PackageManager::class,
            'avatar_color' => IdentityColor::class,
            'push_to_deploy' => 'boolean',
            'auto_source' => 'boolean',
            'zero_downtime' => 'boolean',
            'deployment_retention' => 'integer',
            'last_deployed_at' => 'datetime',
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
     * @return BelongsTo<SourceControl, $this>
     */
    public function sourceControl(): BelongsTo
    {
        return $this->belongsTo(SourceControl::class);
    }

    /**
     * @return BelongsTo<\Nip\UnixUser\Models\UnixUser, $this>
     */
    public function unixUser(): BelongsTo
    {
        return $this->belongsTo(\Nip\UnixUser\Models\UnixUser::class, 'user', 'username')
            ->where('server_id', $this->server_id);
    }

    /**
     * @return HasMany<\Nip\Domain\Models\DomainRecord, $this>
     */
    public function domainRecords(): HasMany
    {
        return $this->hasMany(\Nip\Domain\Models\DomainRecord::class);
    }

    /**
     * @return HasOne<\Nip\Domain\Models\DomainRecord, $this>
     */
    public function primaryDomain(): HasOne
    {
        return $this->hasOne(\Nip\Domain\Models\DomainRecord::class)
            ->where('type', \Nip\Domain\Enums\DomainRecordType::Primary);
    }

    /**
     * @return HasMany<\Nip\Domain\Models\Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(\Nip\Domain\Models\Certificate::class);
    }

    /**
     * @return BelongsTo<\Nip\Database\Models\Database, $this>
     */
    public function database(): BelongsTo
    {
        return $this->belongsTo(\Nip\Database\Models\Database::class);
    }

    /**
     * @return BelongsTo<\Nip\Database\Models\DatabaseUser, $this>
     */
    public function databaseUser(): BelongsTo
    {
        return $this->belongsTo(\Nip\Database\Models\DatabaseUser::class);
    }

    /**
     * @return HasMany<\Nip\Scheduler\Models\ScheduledJob, $this>
     */
    public function scheduledJobs(): HasMany
    {
        return $this->hasMany(\Nip\Scheduler\Models\ScheduledJob::class);
    }

    /**
     * @return HasMany<\Nip\BackgroundProcess\Models\BackgroundProcess, $this>
     */
    public function backgroundProcesses(): HasMany
    {
        return $this->hasMany(\Nip\BackgroundProcess\Models\BackgroundProcess::class);
    }

    /**
     * @return HasMany<\Nip\Security\Models\SecurityRule, $this>
     */
    public function securityRules(): HasMany
    {
        return $this->hasMany(\Nip\Security\Models\SecurityRule::class);
    }

    /**
     * @return HasMany<\Nip\Redirect\Models\RedirectRule, $this>
     */
    public function redirectRules(): HasMany
    {
        return $this->hasMany(\Nip\Redirect\Models\RedirectRule::class);
    }

    /**
     * @return HasMany<ComposerCredential, $this>
     */
    public function composerCredentials(): HasMany
    {
        return $this->hasMany(ComposerCredential::class);
    }

    public function getFullPath(): string
    {
        return "/home/{$this->user}/{$this->domain}";
    }

    public function getCurrentPath(): string
    {
        return "{$this->getFullPath()}/current";
    }

    public function getRootPath(): string
    {
        return $this->getCurrentPath().$this->web_directory;
    }

    public function getWebPath(): string
    {
        $webDir = $this->web_directory === '/' ? '' : $this->web_directory;

        return $this->getFullPath().$webDir;
    }

    public function getEffectivePhpVersion(): string
    {
        $version = $this->php_version ?? $this->server->php_version;

        // If already in numeric format (8.4), return as-is
        if (preg_match('/^\d+\.\d+$/', $version)) {
            return $version;
        }

        // Convert enum value (php84) to numeric version (8.4)
        return PhpVersion::tryFrom($version)?->version() ?? '8.4';
    }

    public function getPhpSocketPath(): string
    {
        $phpVersion = $this->getEffectivePhpVersion();

        return "/var/run/php/php{$phpVersion}-fpm-{$this->user}.sock";
    }

    public function getUrl(): string
    {
        return 'https://'.$this->domain;
    }

    public function getDisplayableRepository(): ?string
    {
        if (! $this->repository) {
            return null;
        }

        $branch = $this->branch ?? 'main';

        return "{$this->repository}:{$branch}";
    }

    public function getDeployHookUrl(): ?string
    {
        if (! $this->deploy_hook_token) {
            return null;
        }

        return url("/deploy/{$this->deploy_hook_token}");
    }

    public function regenerateDeployHookToken(): void
    {
        $this->update([
            'deploy_hook_token' => bin2hex(random_bytes(32)),
        ]);
    }

    public function getAuthenticatedCloneUrl(): ?string
    {
        if (! $this->repository || ! $this->sourceControl) {
            return null;
        }

        $service = new GitHubService($this->sourceControl);

        return $service->getCloneUrl($this->repository);
    }

    public function getCloneUrl(): ?string
    {
        if (! $this->repository) {
            return null;
        }

        if ($this->sourceControl) {
            return $this->getAuthenticatedCloneUrl();
        }

        return "https://github.com/{$this->repository}.git";
    }

    /**
     * @return Attribute<array<SiteProvisioningStepData>, never>
     */
    protected function provisioningSteps(): Attribute
    {
        return Attribute::get(fn () => $this->getProvisioningSteps());
    }

    /**
     * @return array<SiteProvisioningStepData>
     */
    public function getProvisioningSteps(): array
    {
        $steps = [
            SiteProvisioningStep::Initializing,
            SiteProvisioningStep::CreatingSiteConfigDirectory,
            SiteProvisioningStep::CreatingNginxServerBlock,
            SiteProvisioningStep::ConfiguringWwwRedirect,
            SiteProvisioningStep::EnablingNginxSite,
            SiteProvisioningStep::CreatingPhpFpmPool,
            SiteProvisioningStep::RestartingServices,
            SiteProvisioningStep::CreatingLogrotateConfig,
            SiteProvisioningStep::CreatingSiteDirectory,
            SiteProvisioningStep::CloningRepository,
            SiteProvisioningStep::ConfiguringEnvironment,
        ];

        if ($this->repository) {
            $steps[] = SiteProvisioningStep::InstallingComposerDependencies;
        }

        if ($this->repository && $this->build_command) {
            $steps[] = SiteProvisioningStep::BuildingFrontendAssets;
        }

        if ($this->repository && $this->type?->hasMigrations()) {
            $steps[] = SiteProvisioningStep::RunningMigrations;
        }

        $steps[] = SiteProvisioningStep::FinalizingSite;

        return array_map(
            fn (SiteProvisioningStep $step) => new SiteProvisioningStepData(
                value: $step->value,
                label: $step->label(),
                description: $step->description(),
            ),
            $steps
        );
    }
}
